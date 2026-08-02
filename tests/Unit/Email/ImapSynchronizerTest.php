<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Email;

use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;
use Katakata\Email\ImapMailboxSource;
use Katakata\Email\ImapSettings;
use Katakata\Email\ImapSynchronizer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ImapSynchronizerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-imap-sync-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testRepeatedSyncWritesEachUnchangedMessageOnlyOnceWithoutAttachments(): void
    {
        $source = $this->source([[
            'id' => 'uid-100',
            'from' => 'reader@example.com',
            'to' => 'letters@example.com',
            'subject' => 'A reply',
            'text' => 'Hello.',
            'html' => '<p>Hello.</p>',
            'received_at' => '2026-08-02T00:00:00+00:00',
            'attachments' => [[
                'id' => 'attachment-1',
                'name' => 'note.txt',
                'media_type' => 'text/plain',
                'content' => 'Attached note.',
            ]],
        ]]);
        $sync = new ImapSynchronizer($this->settings(), $source, $this->root, new AtomicFile());

        $first = $sync->sync(50, new DateTimeImmutable('2026-08-02T01:00:00+00:00'));
        $messagePath = $this->root . '/messages/uid-100.json';
        $firstBytes = file_get_contents($messagePath);
        $firstMtime = filemtime($messagePath);
        sleep(1);
        $second = $sync->sync(50, new DateTimeImmutable('2026-08-02T01:05:00+00:00'));

        self::assertSame(1, $first['fetched']);
        self::assertSame(1, $first['written']);
        self::assertSame(1, $second['fetched']);
        self::assertSame(0, $second['written']);
        self::assertSame($firstBytes, file_get_contents($messagePath));
        self::assertSame($firstMtime, filemtime($messagePath));
        self::assertStringNotContainsString('attachments', (string) file_get_contents($messagePath));
        self::assertDirectoryDoesNotExist($this->root . '/attachments');
        self::assertSame(0600, fileperms($messagePath) & 0777);
        self::assertSame(0600, fileperms($this->root . '/index.json') & 0777);
    }

    public function testSmallerSyncWindowRetainsExistingUnexpiredMessages(): void
    {
        $first = new ImapSynchronizer($this->settings(), $this->source([
            $this->message('uid-1', '2026-08-01T10:00:00+00:00'),
            $this->message('uid-2', '2026-08-02T10:00:00+00:00'),
        ]), $this->root, new AtomicFile());
        $first->sync(100, new DateTimeImmutable('2026-08-02T12:00:00+00:00'));

        $second = new ImapSynchronizer($this->settings(), $this->source([
            $this->message('uid-2', '2026-08-02T10:00:00+00:00'),
        ]), $this->root, new AtomicFile());
        $second->sync(1, new DateTimeImmutable('2026-08-02T13:00:00+00:00'));

        $index = $this->index();
        self::assertSame(['uid-2', 'uid-1'], $index['messages']);
        self::assertFileExists($this->root . '/messages/uid-1.json');
        self::assertFileExists($this->root . '/messages/uid-2.json');
    }

    public function testSyncPrunesExpiredMessagesLegacyAttachmentsAndLocalState(): void
    {
        mkdir($this->root . '/messages', 0700, true);
        mkdir($this->root . '/attachments/expired-id', 0700, true);
        file_put_contents($this->root . '/attachments/expired-id/file', 'legacy');
        file_put_contents($this->root . '/messages/expired-id.json', json_encode($this->message('expired-id', '2026-06-01T00:00:00+00:00'), JSON_THROW_ON_ERROR));
        file_put_contents($this->root . '/messages/current-id.json', json_encode($this->message('current-id', '2026-08-01T00:00:00+00:00'), JSON_THROW_ON_ERROR));
        file_put_contents($this->root . '/index.json', json_encode(['messages' => ['expired-id', 'current-id'], 'status' => []], JSON_THROW_ON_ERROR));
        file_put_contents($this->root . '/state.json', json_encode(['read' => ['expired-id', 'current-id'], 'archived' => ['expired-id']], JSON_THROW_ON_ERROR));

        $sync = new ImapSynchronizer($this->settings(), $this->source([]), $this->root, new AtomicFile());
        $sync->sync(10, new DateTimeImmutable('2026-08-02T00:00:00+00:00'));

        self::assertFileDoesNotExist($this->root . '/messages/expired-id.json');
        self::assertFileExists($this->root . '/messages/current-id.json');
        self::assertDirectoryDoesNotExist($this->root . '/attachments');
        self::assertSame(['current-id'], $this->index()['messages']);
        $state = json_decode((string) file_get_contents($this->root . '/state.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['current-id'], $state['read']);
        self::assertSame([], $state['archived']);
    }

    public function testFailedSyncPreservesCachedMessagesAndLastSuccessfulTimestamp(): void
    {
        (new ImapSynchronizer($this->settings(), $this->source([
            $this->message('uid-200', '2026-08-02T00:00:00+00:00'),
        ]), $this->root, new AtomicFile()))
            ->sync(50, new DateTimeImmutable('2026-08-02T01:00:00+00:00'));

        $failing = new class implements ImapMailboxSource {
            public function fetch(ImapSettings $settings, int $limit = 100): array
            {
                throw new RuntimeException('Mailbox connection failed.');
            }
        };
        $sync = new ImapSynchronizer($this->settings(), $failing, $this->root, new AtomicFile());

        try {
            $sync->sync(50, new DateTimeImmutable('2026-08-02T02:00:00+00:00'));
            self::fail('Expected the mailbox source failure to propagate.');
        } catch (RuntimeException $error) {
            self::assertSame('Mailbox connection failed.', $error->getMessage());
        }

        $index = $this->index();
        self::assertSame(['uid-200'], $index['messages']);
        self::assertSame('error', $index['status']['state']);
        self::assertSame('Mailbox connection failed.', $index['status']['error']);
        self::assertSame('2026-08-02T01:00:00+00:00', $index['status']['last_synced_at']);
        self::assertFileExists($this->root . '/messages/uid-200.json');
    }

    public function testIncompleteDeploymentSettingsProduceNeedsSetupStateWithoutCallingSource(): void
    {
        $source = new class implements ImapMailboxSource {
            public bool $called = false;

            public function fetch(ImapSettings $settings, int $limit = 100): array
            {
                $this->called = true;
                return [];
            }
        };
        $settings = new ImapSettings('', 993, 'ssl', '', '', 'INBOX');
        $sync = new ImapSynchronizer($settings, $source, $this->root, new AtomicFile());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('IMAP deployment variables are incomplete.');

        try {
            $sync->sync();
        } finally {
            $index = $this->index();
            self::assertFalse($source->called);
            self::assertSame([], $index['messages']);
            self::assertSame('needs_setup', $index['status']['state']);
            self::assertSame('IMAP deployment variables are incomplete.', $index['status']['error']);
        }
    }

    private function settings(): ImapSettings
    {
        return new ImapSettings('imap.example.com', 993, 'ssl', 'letters@example.com', 'secret', 'INBOX');
    }

    /** @param list<array<string,mixed>> $messages */
    private function source(array $messages): ImapMailboxSource
    {
        return new class($messages) implements ImapMailboxSource {
            public function __construct(private readonly array $messages)
            {
            }

            public function fetch(ImapSettings $settings, int $limit = 100): array
            {
                return array_slice($this->messages, 0, $limit);
            }
        };
    }

    /** @return array<string,mixed> */
    private function message(string $id, string $receivedAt): array
    {
        return [
            'id' => $id,
            'from' => 'reader@example.com',
            'to' => 'letters@example.com',
            'subject' => 'Reply ' . $id,
            'text' => 'Hello.',
            'html' => null,
            'received_at' => $receivedAt,
            'attachments' => [],
        ];
    }

    /** @return array<string,mixed> */
    private function index(): array
    {
        return json_decode((string) file_get_contents($this->root . '/index.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $target = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($target) ? $this->remove($target) : @unlink($target);
        }
        @rmdir($path);
    }
}

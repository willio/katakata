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

    public function testRepeatedSyncWritesEachUnchangedMessageOnlyOnce(): void
    {
        $source = new class implements ImapMailboxSource {
            public function fetch(ImapSettings $settings, int $limit = 100): array
            {
                return [[
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
                ]];
            }
        };
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
        self::assertSame('Attached note.', file_get_contents($this->root . '/attachments/uid-100/attachment-1'));
        self::assertSame(0600, fileperms($messagePath) & 0777);
        self::assertSame(0600, fileperms($this->root . '/index.json') & 0777);
    }

    public function testFailedSyncPreservesCachedMessagesAndLastSuccessfulTimestamp(): void
    {
        $successful = new class implements ImapMailboxSource {
            public function fetch(ImapSettings $settings, int $limit = 100): array
            {
                return [[
                    'id' => 'uid-200',
                    'from' => 'reader@example.com',
                    'to' => 'letters@example.com',
                    'subject' => 'Cached reply',
                    'text' => 'Keep this.',
                    'html' => null,
                    'received_at' => '2026-08-02T00:00:00+00:00',
                    'attachments' => [],
                ]];
            }
        };
        (new ImapSynchronizer($this->settings(), $successful, $this->root, new AtomicFile()))
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

        $index = json_decode((string) file_get_contents($this->root . '/index.json'), true, 512, JSON_THROW_ON_ERROR);
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
            $index = json_decode((string) file_get_contents($this->root . '/index.json'), true, 512, JSON_THROW_ON_ERROR);
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

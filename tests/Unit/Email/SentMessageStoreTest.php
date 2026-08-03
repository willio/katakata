<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Email;

use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;
use Katakata\Email\Draft;
use Katakata\Email\DraftSender;
use Katakata\Email\DraftStore;
use Katakata\Email\OutboundMailProvider;
use Katakata\Email\SentMessageStore;
use PHPUnit\Framework\TestCase;

final class SentMessageStoreTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-sent-mail-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testSuccessfulDeliveryCreatesOnePrivateSentRecordAndDeletesDraft(): void
    {
        $createdAt = new DateTimeImmutable('2026-08-02T00:00:00+00:00');
        $draft = new Draft(
            id: 'draft-1',
            to: 'reader@example.test',
            subject: 'Reply',
            text: 'Thank you.',
            inReplyTo: 'message-1',
            version: 1,
            createdAt: $createdAt,
            updatedAt: $createdAt,
        );
        $drafts = new class($draft) implements DraftStore {
            public bool $deleted = false;
            public function __construct(private ?Draft $draft) {}
            public function create(Draft $draft): Draft { $this->draft = $draft; return $draft; }
            public function save(Draft $draft, int $expectedVersion): Draft { $this->draft = $draft; return $draft; }
            public function find(string $id): ?Draft { return $this->draft?->id === $id ? $this->draft : null; }
            public function recent(int $limit = 8): array { return $this->draft === null ? [] : [$this->draft]; }
            public function delete(string $id): void { $this->deleted = true; $this->draft = null; }
            public function deleteIfVersion(string $id, int $expectedVersion): bool
            {
                if ($this->draft?->id !== $id || $this->draft->version !== $expectedVersion) {
                    return false;
                }
                $this->deleted = true;
                $this->draft = null;
                return true;
            }
        };
        $outbound = new class implements OutboundMailProvider {
            public int $calls = 0;
            public function send(Draft $draft): void { $this->calls++; }
        };
        $sent = new SentMessageStore($this->root, new AtomicFile());

        (new DraftSender($drafts, $outbound, $sent))->send('draft-1');

        self::assertSame(1, $outbound->calls);
        self::assertTrue($drafts->deleted);
        self::assertCount(1, $sent->recent());
        self::assertSame('reader@example.test', $sent->recent()[0]->to);
        self::assertSame('Reply', $sent->recent()[0]->subject);
        self::assertSame('message-1', $sent->recent()[0]->inReplyTo);
        self::assertSame(0600, fileperms($this->root . '/draft-1.json') & 0777);
    }

    public function testFailedDeliveryDoesNotCreateSentRecordOrDeleteDraft(): void
    {
        $createdAt = new DateTimeImmutable('2026-08-02T00:00:00+00:00');
        $draft = new Draft(
            id: 'draft-2',
            to: 'reader@example.test',
            subject: 'Reply',
            text: 'Body',
            inReplyTo: null,
            version: 1,
            createdAt: $createdAt,
            updatedAt: $createdAt,
        );
        $drafts = new class($draft) implements DraftStore {
            public bool $deleted = false;
            public function __construct(private ?Draft $draft) {}
            public function create(Draft $draft): Draft { $this->draft = $draft; return $draft; }
            public function save(Draft $draft, int $expectedVersion): Draft { $this->draft = $draft; return $draft; }
            public function find(string $id): ?Draft { return $this->draft?->id === $id ? $this->draft : null; }
            public function recent(int $limit = 8): array { return $this->draft === null ? [] : [$this->draft]; }
            public function delete(string $id): void { $this->deleted = true; $this->draft = null; }
            public function deleteIfVersion(string $id, int $expectedVersion): bool
            {
                if ($this->draft?->id !== $id || $this->draft->version !== $expectedVersion) {
                    return false;
                }
                $this->deleted = true;
                $this->draft = null;
                return true;
            }
        };
        $outbound = new class implements OutboundMailProvider {
            public function send(Draft $draft): void { throw new \RuntimeException('Delivery failed.'); }
        };
        $sent = new SentMessageStore($this->root, new AtomicFile());

        try {
            (new DraftSender($drafts, $outbound, $sent))->send('draft-2');
            self::fail('Expected delivery failure.');
        } catch (\RuntimeException $error) {
            self::assertSame('Delivery failed.', $error->getMessage());
        }

        self::assertFalse($drafts->deleted);
        self::assertSame([], $sent->recent());
    }

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            @unlink($path);
            return;
        }
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->remove($path . DIRECTORY_SEPARATOR . $item);
        }
        @rmdir($path);
    }
}

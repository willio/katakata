<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Email;

use DateTimeImmutable;
use Katakata\Email\AttachmentDownload;
use Katakata\Email\MailboxProvider;
use Katakata\Email\Message;
use Katakata\Email\MessageSummary;
use Katakata\Email\Providers\AggregatedMailboxProvider;
use PHPUnit\Framework\TestCase;

final class AggregatedMailboxProviderTest extends TestCase
{
    public function testItMergesAccountsChronologicallyAndRoutesMutationsByQualifiedId(): void
    {
        $letters = $this->provider('letters:uid-1', 'Letters', '2026-08-01T10:00:00+00:00');
        $editorial = $this->provider('editorial:uid-1', 'Editorial', '2026-08-02T10:00:00+00:00');
        $mailbox = new AggregatedMailboxProvider([
            'letters' => $letters,
            'editorial' => $editorial,
        ]);

        $messages = $mailbox->inbox();

        self::assertSame(['editorial:uid-1', 'letters:uid-1'], array_column($messages, 'id'));
        self::assertSame(['Editorial', 'Letters'], array_column($messages, 'sourceLabel'));
        self::assertSame(2, $mailbox->unreadCount());

        $mailbox->markRead('letters:uid-1', true);
        $mailbox->archive('editorial:uid-1');
        $mailbox->deleteLocal('letters:uid-1');

        self::assertSame([['letters:uid-1', true]], $letters->marked);
        self::assertSame(['editorial:uid-1'], $editorial->archived);
        self::assertSame(['letters:uid-1'], $letters->deleted);
        self::assertSame([], $editorial->marked);
        self::assertSame([], $letters->archived);
    }

    public function testItReportsPartialReadinessWithoutHidingHealthyMail(): void
    {
        $ready = $this->provider('letters:uid-2', 'Letters', '2026-08-02T10:00:00+00:00', 'ready');
        $failed = $this->provider('admin:uid-2', 'Admin', '2026-08-01T10:00:00+00:00', 'error');
        $mailbox = new AggregatedMailboxProvider(['letters' => $ready, 'admin' => $failed]);

        self::assertCount(2, $mailbox->inbox());
        self::assertSame('partial', $mailbox->readiness()['status']);
    }

    private function provider(string $id, string $label, string $receivedAt, string $status = 'ready'): MailboxProvider
    {
        return new class($id, $label, $receivedAt, $status) implements MailboxProvider {
            /** @var list<array{string,bool}> */
            public array $marked = [];
            /** @var list<string> */
            public array $archived = [];
            /** @var list<string> */
            public array $deleted = [];

            public function __construct(
                private readonly string $id,
                private readonly string $label,
                private readonly string $receivedAt,
                private readonly string $status,
            ) {
            }

            public function inbox(int $limit = 50): array
            {
                [$account, $local] = explode(':', $this->id, 2);
                return [new MessageSummary(
                    id: $this->id,
                    from: 'reader@example.test',
                    subject: $this->label,
                    receivedAt: new DateTimeImmutable($this->receivedAt),
                    unread: true,
                    sourceAccountId: $account,
                    sourceLabel: $this->label,
                    sourceMessageId: $local,
                )];
            }

            public function unreadCount(): int
            {
                return 1;
            }

            public function message(string $id): ?Message
            {
                return null;
            }

            public function attachment(string $messageId, string $attachmentId): ?AttachmentDownload
            {
                return null;
            }

            public function markRead(string $id, bool $read): void
            {
                $this->marked[] = [$id, $read];
            }

            public function archive(string $id): void
            {
                $this->archived[] = $id;
            }

            public function deleteLocal(string $id): void
            {
                $this->deleted[] = $id;
            }

            public function readiness(): array
            {
                return [
                    'status' => $this->status,
                    'reason' => $this->status === 'ready' ? null : 'Sync failed.',
                    'last_synced_at' => $this->status === 'ready' ? $this->receivedAt : null,
                ];
            }
        };
    }
}

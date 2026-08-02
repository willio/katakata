<?php

declare(strict_types=1);

namespace Katakata\Email\Providers;

use Katakata\Email\AttachmentDownload;
use Katakata\Email\MailboxProvider;
use Katakata\Email\Message;

final class UnavailableMailboxProvider implements MailboxProvider
{
    public function inbox(int $limit = 50): array
    {
        return [];
    }

    public function unreadCount(): int
    {
        return 0;
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
    }

    public function archive(string $id): void
    {
    }

    public function deleteLocal(string $id): void
    {
    }

    public function readiness(): array
    {
        return [
            'status' => 'needs_setup',
            'reason' => 'IMAP inbox configuration is deployment-only and has not been enabled.',
            'last_synced_at' => null,
        ];
    }
}

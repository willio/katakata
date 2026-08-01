<?php

declare(strict_types=1);

namespace Katakata\Email;

final class Mailbox
{
    public function __construct(private readonly MailboxProvider $provider)
    {
    }

    public function inbox(int $limit = 50): array
    {
        return $this->provider->inbox(max(1, $limit));
    }

    public function unreadCount(): int
    {
        return $this->provider->unreadCount();
    }

    public function message(string $id): ?Message
    {
        return $this->provider->message($id);
    }

    public function attachment(string $messageId, string $attachmentId): ?AttachmentDownload
    {
        return $this->provider->attachment($messageId, $attachmentId);
    }

    public function markRead(string $id, bool $read): void
    {
        $this->provider->markRead($id, $read);
    }

    public function archive(string $id): void
    {
        $this->provider->archive($id);
    }

    public function readiness(): array
    {
        return $this->provider->readiness();
    }
}

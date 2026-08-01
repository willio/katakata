<?php

declare(strict_types=1);

namespace Katakata\Email;

final class Mailbox
{
    public function __construct(private readonly MailboxProvider $provider)
    {
    }

    /** @return list<MessageSummary> */
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

    /** @return array{status:string,reason:?string,last_synced_at:?string} */
    public function readiness(): array
    {
        return $this->provider->readiness();
    }
}

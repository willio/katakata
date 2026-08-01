<?php

declare(strict_types=1);

namespace Katakata\Email;

interface MailboxProvider
{
    /** @return list<MessageSummary> */
    public function inbox(int $limit = 50): array;

    public function unreadCount(): int;

    public function message(string $id): ?Message;

    public function attachment(string $messageId, string $attachmentId): ?AttachmentDownload;

    public function markRead(string $id, bool $read): void;

    public function archive(string $id): void;

    /** @return array{status:string,reason:?string,last_synced_at:?string} */
    public function readiness(): array;
}

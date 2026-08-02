<?php

declare(strict_types=1);

namespace Katakata\Email;

use DateTimeImmutable;

final readonly class Message
{
    /** @param list<Attachment> $attachments */
    public function __construct(
        public string $id,
        public string $from,
        public string $to,
        public string $subject,
        public string $text,
        public ?string $html,
        public DateTimeImmutable $receivedAt,
        public bool $unread,
        public array $attachments = [],
        public ?string $sourceAccountId = null,
        public ?string $sourceLabel = null,
        public ?string $sourceMessageId = null,
    ) {
    }

    public function summary(): MessageSummary
    {
        return new MessageSummary(
            id: $this->id,
            from: $this->from,
            subject: $this->subject,
            receivedAt: $this->receivedAt,
            unread: $this->unread,
            sourceAccountId: $this->sourceAccountId,
            sourceLabel: $this->sourceLabel,
            sourceMessageId: $this->sourceMessageId,
            text: $this->text,
            to: $this->to,
        );
    }
}

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
    ) {
    }

    public function summary(): MessageSummary
    {
        return new MessageSummary($this->id, $this->from, $this->subject, $this->receivedAt, $this->unread);
    }
}

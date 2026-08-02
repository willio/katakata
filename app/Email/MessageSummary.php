<?php

declare(strict_types=1);

namespace Katakata\Email;

use DateTimeImmutable;

final readonly class MessageSummary
{
    public function __construct(
        public string $id,
        public string $from,
        public string $subject,
        public DateTimeImmutable $receivedAt,
        public bool $unread = true,
        public ?string $sourceAccountId = null,
        public ?string $sourceLabel = null,
        public ?string $sourceMessageId = null,
    ) {
    }
}

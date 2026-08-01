<?php

declare(strict_types=1);

namespace Katakata\Email;

use DateTimeImmutable;

final readonly class Draft
{
    public function __construct(
        public string $id,
        public string $to,
        public string $subject,
        public string $text,
        public ?string $inReplyTo,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}

<?php
declare(strict_types=1);

namespace Katakata\Discussion;

use DateTimeImmutable;

final class DiscussionEntry
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public readonly string $id,
        public readonly string $authorName,
        public readonly string $body,
        public readonly DateTimeImmutable $publishedAt,
        public readonly ?string $authorUrl = null,
        public readonly ?string $parentId = null,
        public readonly array $metadata = [],
    ) {
    }
}

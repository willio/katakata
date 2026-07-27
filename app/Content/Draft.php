<?php

declare(strict_types=1);

namespace Katakata\Content;

use DateTimeImmutable;

/**
 * An unpublished, in-progress article.
 *
 * Drafts live under content/drafts/ with no date-based folder
 * structure, since they haven't been assigned a publish date yet.
 */
final class Draft
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly ?DateTimeImmutable $updatedAt,
        public readonly string $body,
        public readonly array $meta,
        public readonly string $path,
    ) {
    }
}

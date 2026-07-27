<?php

declare(strict_types=1);

namespace Katakata\Content;

use DateTimeImmutable;

/**
 * A published article.
 *
 * Post objects are produced only by the Repository — application
 * code never constructs one from raw Markdown directly, per the
 * Master Specification: "Applications never read Markdown directly.
 * Everything goes through the repository."
 */
final class Post
{
    /**
     * @param array<int, string> $tags
     * @param array<string, mixed> $meta raw front matter, for fields not yet promoted to first-class properties
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly DateTimeImmutable $date,
        public readonly ?string $author,
        public readonly array $tags,
        public readonly ?string $excerpt,
        public readonly string $status,
        public readonly string $body,
        public readonly array $meta,
        public readonly string $path,
    ) {
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * The article's permanent path, per the Master Specification's
     * content storage convention.
     */
    public function url(): string
    {
        return sprintf('/%s/%s/%s', $this->date->format('Y'), $this->date->format('m'), $this->slug);
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Content;

/**
 * An author.
 *
 * Authors remain ordinary Markdown documents, per the Master
 * Specification's "Multi-Author" section. A future Threads identity
 * or newsletter preferences live in `meta` until they earn dedicated
 * properties.
 */
final class Author
{
    /**
     * @param array<string, mixed> $meta
     * @param list<string> $social
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly ?string $bio,
        public readonly ?string $avatar,
        public readonly array $meta,
        public readonly string $path,
        public readonly array $social = [],
    ) {
    }
}

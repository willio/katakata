<?php

declare(strict_types=1);

namespace Katakata\Content;

/**
 * A binary or static asset referenced by content, e.g. images.
 *
 * Assets carry no front matter — they're discovered by presence on
 * disk under content/assets/, not parsed.
 */
final class Asset
{
    public function __construct(
        public readonly string $filename,
        public readonly string $path,
        public readonly int $bytes,
        public readonly string $mimeType,
    ) {
    }
}

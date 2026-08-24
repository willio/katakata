<?php

declare(strict_types=1);

namespace Katakata\Editorial;

final class TrashItem
{
    /** @param array<string, mixed> $manifest */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $slug,
        public readonly string $title,
        public readonly string $originalPath,
        public readonly string $trashedAt,
        public readonly string $actorId,
        public readonly ?string $reason,
        public readonly string $sha256,
        public readonly array $manifest,
    ) {
    }
}

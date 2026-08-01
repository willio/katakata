<?php
declare(strict_types=1);

namespace Katakata\Discussion;

final class DiscussionReference
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public readonly string $provider,
        public readonly string $id,
        public readonly ?string $url = null,
        public readonly array $metadata = [],
    ) {
    }
}

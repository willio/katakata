<?php
declare(strict_types=1);

namespace Katakata\Discussion;

final class DiscussionThread
{
    /** @param list<DiscussionEntry> $entries */
    public function __construct(
        public readonly DiscussionReference $reference,
        public readonly array $entries = [],
        public readonly ?string $nextCursor = null,
    ) {
    }
}

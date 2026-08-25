<?php

declare(strict_types=1);

namespace Katakata\Discussion;

interface DiscussionFinder
{
    /** @param array<string, mixed> $post */
    public function find(array $post): ?DiscussionThread;
}

<?php

declare(strict_types=1);

namespace Katakata\Distribution;

interface ThreadsInsightsApi
{
    /** @return array{views: int, likes: int, replies: int, reposts: int, quotes: int, shares: int} */
    public function insights(string $mediaId): array;
}

<?php

declare(strict_types=1);

namespace Katakata\Dashboard;

use Katakata\Distribution\ThreadsStore;
use Throwable;

final class DashboardBuzz
{
    public function __construct(
        private readonly ThreadsStore $store,
        private readonly bool $enabled,
    ) {
    }

    /**
     * Null means unavailable; an empty list means available with no replies yet.
     *
     * @return list<array{id: string, post_slug: string, text: string, username: string, timestamp: string, permalink: string, avatar_url: ?string}>|null
     */
    public function recent(int $limit = 8): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        try {
            return $this->store->recentReplies($limit);
        } catch (Throwable) {
            return null;
        }
    }
}

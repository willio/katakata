<?php

declare(strict_types=1);

namespace Katakata\Dashboard;

use Katakata\Discussion\DiscussionEntry;
use Katakata\Discussion\DiscussionManager;
use Throwable;

final class DashboardBuzz
{
    public function __construct(
        private readonly DiscussionManager $discussions,
        private readonly string $provider = 'none',
    ) {
    }

    /**
     * Null means unavailable; an empty list means available with no replies yet.
     *
     * @return list<array{id: string, post_slug: string, text: string, username: string, timestamp: string, permalink: string, avatar_url: ?string}>|null
     */
    public function recent(int $limit = 8): ?array
    {
        try {
            $provider = $this->discussions->resolve($this->provider);
            if ($provider->key() === 'none') {
                return null;
            }

            return array_map(
                static fn (DiscussionEntry $entry): array => [
                    'id' => $entry->id,
                    'post_slug' => (string) ($entry->metadata['post_slug'] ?? ''),
                    'text' => $entry->body,
                    'username' => $entry->authorName,
                    'timestamp' => $entry->publishedAt->format(DATE_ATOM),
                    'permalink' => (string) ($entry->metadata['permalink'] ?? $entry->authorUrl ?? ''),
                    'avatar_url' => isset($entry->metadata['avatar_url']) ? (string) $entry->metadata['avatar_url'] : null,
                ],
                $provider->recent($limit),
            );
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Backwards-compatible alias for dashboard callers.
     *
     * @return list<array{id: string, post_slug: string, text: string, username: string, timestamp: string, permalink: string, avatar_url: ?string}>|null
     */
    public function latest(int $limit = 8): ?array
    {
        return $this->recent($limit);
    }
}

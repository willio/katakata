<?php

declare(strict_types=1);

namespace Katakata\Distribution;

use Katakata\Editorial\AtomicFile;

final class ThreadsStore
{
    public function __construct(
        private readonly string $path,
        private readonly AtomicFile $files,
    ) {
    }

    /** @param array{id: string, permalink: ?string} $publication */
    public function remember(string $postSlug, array $publication): void
    {
        $state = $this->read();
        $state['publications'][$postSlug] = [
            'media_id' => $publication['id'],
            'permalink' => $publication['permalink'],
            'published_at' => gmdate(DATE_ATOM),
        ];
        $this->write($state);
    }

    /** @return array<string, array{media_id: string, permalink: ?string, published_at: string}> */
    public function publications(): array
    {
        return $this->read()['publications'];
    }

    /** @return array{media_id: string, permalink: ?string, published_at: string}|null */
    public function publication(string $postSlug): ?array
    {
        return $this->publications()[$postSlug] ?? null;
    }

    /** @return list<array{id: string, text: string, username: string, timestamp: string, permalink: string, avatar_url: ?string}> */
    public function replies(string $postSlug): array
    {
        return $this->read()['replies'][$postSlug] ?? [];
    }

    /** @param list<array{id: string, text: string, username: string, timestamp: string, permalink: string, avatar_url: ?string}> $replies */
    public function replaceReplies(string $postSlug, array $replies): void
    {
        $state = $this->read();
        $state['replies'][$postSlug] = $replies;
        $state['synced_at'] = gmdate(DATE_ATOM);
        $this->write($state);
    }

    /** @param array{views: int, likes: int, replies: int, reposts: int, quotes: int, shares: int} $metrics */
    public function replaceEngagement(string $postSlug, array $metrics): void
    {
        $state = $this->read();
        $state['engagement'][$postSlug] = [
            'metrics' => $metrics,
            'synced_at' => gmdate(DATE_ATOM),
        ];
        $this->write($state);
    }

    /** @return array<string, array{metrics: array{views: int, likes: int, replies: int, reposts: int, quotes: int, shares: int}, synced_at: string}> */
    public function engagement(): array
    {
        return $this->read()['engagement'];
    }

    /** @return list<array{id: string, post_slug: string, text: string, username: string, timestamp: string, permalink: string, avatar_url: ?string}> */
    public function recentReplies(int $limit = 8): array
    {
        $all = [];
        foreach ($this->read()['replies'] as $slug => $replies) {
            foreach ($replies as $reply) {
                $reply['post_slug'] = $slug;
                $all[$reply['id']] = $reply;
            }
        }
        usort($all, static fn (array $left, array $right): int => strcmp($right['timestamp'], $left['timestamp']));
        return array_slice(array_values($all), 0, max(0, $limit));
    }

    /** @return array{version: int, publications: array<string, array{media_id: string, permalink: ?string, published_at: string}>, replies: array<string, list<array{id: string, text: string, username: string, timestamp: string, permalink: string, avatar_url: ?string}>>, engagement: array<string, array{metrics: array{views: int, likes: int, replies: int, reposts: int, quotes: int, shares: int}, synced_at: string}>, synced_at: ?string} */
    private function read(): array
    {
        if (!is_file($this->path)) {
            return ['version' => 2, 'publications' => [], 'replies' => [], 'engagement' => [], 'synced_at' => null];
        }
        $decoded = json_decode((string) file_get_contents($this->path), true);
        return is_array($decoded)
            ? array_replace(['version' => 2, 'publications' => [], 'replies' => [], 'engagement' => [], 'synced_at' => null], $decoded)
            : ['version' => 1, 'publications' => [], 'replies' => [], 'synced_at' => null];
    }

    /** @param array<string, mixed> $state */
    private function write(array $state): void
    {
        $this->files->write(
            $this->path,
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        );
    }
}

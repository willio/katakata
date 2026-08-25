<?php

declare(strict_types=1);

namespace Katakata\Discussion\Providers;

use DateTimeImmutable;
use InvalidArgumentException;
use Katakata\Discussion\DiscussionEntry;
use Katakata\Discussion\DiscussionFinder;
use Katakata\Discussion\DiscussionProvider;
use Katakata\Discussion\DiscussionReference;
use Katakata\Discussion\DiscussionThread;
use Katakata\Distribution\ThreadsApi;
use Katakata\Distribution\ThreadsStore;

final class ThreadsDiscussionProvider implements DiscussionProvider, DiscussionFinder
{
    public function __construct(
        private readonly ThreadsApi $api,
        private readonly ThreadsStore $store,
        private readonly bool $enabled = true,
    ) {
    }

    public function key(): string
    {
        return 'threads';
    }

    public function isAvailable(): bool
    {
        return $this->enabled;
    }

    public function supportsReplies(): bool
    {
        return true;
    }

    public function create(array $post): DiscussionReference
    {
        $slug = trim((string) ($post['slug'] ?? ''));
        $text = trim((string) ($post['discussion_text'] ?? $post['title'] ?? ''));
        if ($slug === '' || $text === '') {
            throw new InvalidArgumentException('Threads discussion creation requires post slug and text.');
        }

        $publication = $this->api->publish($text);
        $this->store->remember($slug, $publication);

        return new DiscussionReference(
            provider: $this->key(),
            id: $publication['id'],
            url: $publication['permalink'],
            metadata: ['post_slug' => $slug],
        );
    }

    public function find(array $post): ?DiscussionThread
    {
        $slug = trim((string) ($post['slug'] ?? ''));
        if ($slug === '') {
            throw new InvalidArgumentException('Threads discussion lookup requires a post slug.');
        }

        $publication = $this->store->publication($slug);
        if ($publication === null) {
            return null;
        }

        $reference = new DiscussionReference(
            provider: $this->key(),
            id: $publication['media_id'],
            url: $publication['permalink'],
            metadata: ['post_slug' => $slug],
        );

        return new DiscussionThread(
            $reference,
            array_map(fn (array $row): DiscussionEntry => $this->normalize($row, $slug), $this->store->replies($slug)),
        );
    }

    public function fetch(DiscussionReference $reference): DiscussionThread
    {
        $rows = $this->api->replies($reference->id);
        $slug = trim((string) ($reference->metadata['post_slug'] ?? ''));
        if ($slug !== '') {
            $this->store->replaceReplies($slug, $rows);
        }

        return new DiscussionThread(
            $reference,
            array_map(fn (array $row): DiscussionEntry => $this->normalize($row, $slug !== '' ? $slug : null), $rows),
        );
    }

    public function recent(int $limit = 8): array
    {
        return array_map(
            fn (array $row): DiscussionEntry => $this->normalize($row, $row['post_slug'] ?? null),
            $this->store->recentReplies($limit),
        );
    }

    public function synchronize(): array
    {
        $threads = 0;
        $entries = 0;
        $failed = 0;

        foreach ($this->store->publications() as $slug => $publication) {
            try {
                $thread = $this->fetch(new DiscussionReference(
                    $this->key(),
                    $publication['media_id'],
                    $publication['permalink'],
                    ['post_slug' => $slug],
                ));
                $threads++;
                $entries += count($thread->entries);
            } catch (\Throwable) {
                $failed++;
            }
        }

        return ['threads' => $threads, 'entries' => $entries, 'failed' => $failed];
    }

    /** @param array<string, mixed> $row */
    private function normalize(array $row, ?string $postSlug = null): DiscussionEntry
    {
        return new DiscussionEntry(
            id: (string) $row['id'],
            authorName: (string) $row['username'],
            body: (string) $row['text'],
            publishedAt: new DateTimeImmutable((string) $row['timestamp']),
            authorUrl: (string) $row['permalink'],
            metadata: array_filter([
                'provider' => $this->key(),
                'post_slug' => $postSlug,
                'avatar_url' => $row['avatar_url'] ?? null,
                'permalink' => $row['permalink'],
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        );
    }
}

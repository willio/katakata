<?php

declare(strict_types=1);

namespace Katakata\Discussion;

use RuntimeException;

final class NativeDiscussionProvider implements DiscussionProvider, DiscussionFinder
{
    public function __construct(private readonly NativeDiscussionStore $store)
    {
    }

    public function key(): string
    {
        return 'native';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function supportsReplies(): bool
    {
        return true;
    }

    public function create(array $post): DiscussionReference
    {
        $slug = trim((string) ($post['slug'] ?? ''));
        if ($slug === '') {
            throw new RuntimeException('Published post slug is required to create a native discussion.');
        }

        return $this->store->create($slug, $slug);
    }

    public function find(array $post): ?DiscussionThread
    {
        $reference = $this->create($post);

        return $this->fetch($reference);
    }

    public function fetch(DiscussionReference $reference): DiscussionThread
    {
        return $this->store->fetch($reference);
    }

    public function recent(int $limit = 8): array
    {
        return $this->store->recentApproved($limit);
    }

    public function synchronize(): array
    {
        return ['threads' => 0, 'entries' => 0, 'failed' => 0];
    }
}

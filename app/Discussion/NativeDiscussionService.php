<?php

declare(strict_types=1);

namespace Katakata\Discussion;

use Katakata\Content\Post;
use RuntimeException;

final class NativeDiscussionService
{
    public function __construct(
        private readonly NativeDiscussionProvider $provider,
        private readonly NativeDiscussionStore $store,
    ) {
    }

    /** @return array{reference: DiscussionReference, thread: DiscussionThread} */
    public function forPost(Post $post): array
    {
        $reference = $this->provider->create(['slug' => $post->slug]);

        return [
            'reference' => $reference,
            'thread' => $this->provider->fetch($reference),
        ];
    }

    /** @param array<string, mixed> $spam */
    public function submit(
        Post $post,
        string $authorName,
        string $body,
        ?string $parentId = null,
        array $spam = [],
    ): DiscussionEntry {
        $discussion = $this->forPost($post);

        if ($parentId !== null && $parentId !== '') {
            foreach ($discussion['thread']->entries as $entry) {
                if ($entry->id === $parentId) {
                    return $this->store->submit($discussion['reference'], $authorName, $body, $parentId, $spam);
                }
            }

            throw new RuntimeException('Reply target was not found.');
        }

        return $this->store->submit($discussion['reference'], $authorName, $body, null, $spam);
    }
}

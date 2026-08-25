<?php

declare(strict_types=1);

namespace Katakata\Discussion;

use Katakata\Content\Post;

final class PublicDiscussion
{
    public function __construct(
        private readonly DiscussionManager $manager,
        private readonly string $provider,
        private readonly bool $enabledByDefault = false,
    ) {
    }

    /** @return array{reference: DiscussionReference, thread: DiscussionThread}|null */
    public function forPost(Post $post): ?array
    {
        $enabled = array_key_exists('discussion_enabled', $post->meta)
            ? filter_var($post->meta['discussion_enabled'], FILTER_VALIDATE_BOOL)
            : $this->enabledByDefault;
        if (!$enabled) {
            return null;
        }

        $provider = $this->manager->resolve($this->provider);
        if (!$provider instanceof DiscussionFinder) {
            return null;
        }

        $thread = $provider->find(['slug' => $post->slug]);
        if ($thread === null) {
            return null;
        }

        return ['reference' => $thread->reference, 'thread' => $thread];
    }
}

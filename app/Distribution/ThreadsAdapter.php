<?php

declare(strict_types=1);

namespace Katakata\Distribution;

use Katakata\Content\Post;

final class ThreadsAdapter implements Adapter
{
    public function __construct(
        private readonly ThreadsApi $api,
        private readonly ThreadsStore $store,
        private readonly string $appUrl,
    ) {
    }

    public function channel(): string
    {
        return 'threads';
    }

    public function distribute(Post $post): array
    {
        $url = rtrim($this->appUrl, '/') . $post->url();
        $lead = trim($post->excerpt ?? $post->title);
        $text = $lead . "\n\n" . $url;
        if (mb_strlen($text) > 500) {
            $available = 500 - mb_strlen("\n\n" . $url . '…');
            $text = mb_substr($lead, 0, max(0, $available)) . "…\n\n" . $url;
        }

        $publication = $this->api->publish($text);
        $this->store->remember($post->slug, $publication);

        return ['id' => $publication['id'], 'permalink' => $publication['permalink'], 'canonical_url' => $url];
    }
}

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
        $length = static fn (string $value): int => function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        $slice = static fn (string $value, int $limit): string => function_exists('mb_substr')
            ? mb_substr($value, 0, $limit)
            : substr($value, 0, $limit);
        if ($length($text) > 500) {
            $available = 500 - $length("\n\n" . $url . '…');
            $text = $slice($lead, max(0, $available)) . "…\n\n" . $url;
        }

        $publication = $this->api->publish($text);
        $this->store->remember($post->slug, $publication);

        return ['id' => $publication['id'], 'permalink' => $publication['permalink'], 'canonical_url' => $url];
    }
}

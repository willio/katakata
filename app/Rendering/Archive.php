<?php

declare(strict_types=1);
namespace Katakata\Rendering;

use Katakata\Content\Collection;
use Katakata\Content\Post;

final class Archive
{
    /**
     * @param Collection<Post> $posts
     * @return array<int, array<int, Post>>
     */
    public function years(Collection $posts, ?string $query = null): array
    {
        $years = [];
        $query = trim((string) $query);

        foreach ($posts as $post) {
            if (!$post->isPublished() || !$this->matches($post, $query)) {
                continue;
            }

            $years[$post->date->format('Y')][] = $post;
        }

        return $years;
    }

    private function matches(Post $post, string $query): bool
    {
        if ($query === '') {
            return true;
        }

        $haystack = implode("\n", [
            $post->title,
            $post->excerpt ?? '',
            $post->author ?? '',
            implode(' ', $post->tags),
            $post->body,
        ]);

        return mb_stripos($haystack, $query) !== false;
    }
}

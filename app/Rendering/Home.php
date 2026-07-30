<?php

declare(strict_types=1);

namespace Katakata\Rendering;

use Katakata\Content\Collection;
use Katakata\Content\Post;

final class Home
{
    /**
     * @param Collection<Post> $posts
     * @return array<int, Post>
     */
    public function latest(Collection $posts, int $limit = 6): array
    {
        if ($limit < 1) {
            return [];
        }

        $published = array_filter(
            $posts->all(),
            static fn (Post $post): bool => $post->isPublished(),
        );

        usort(
            $published,
            static fn (Post $left, Post $right): int => $right->date <=> $left->date,
        );

        return array_slice($published, 0, $limit);
    }
}

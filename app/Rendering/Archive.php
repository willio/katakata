<?php

declare(strict_types=1);

namespace Katakata\Rendering;

use Katakata\Content\Collection;
use Katakata\Content\Post;

final class Archive
{
    /**
     * @param Collection<Post> $posts
     * @return array<string, array<int, Post>>
     */
    public function years(Collection $posts): array
    {
        $years = [];

        foreach ($posts as $post) {
            if (!$post->isPublished()) {
                continue;
            }

            $years[$post->date->format('Y')][] = $post;
        }

        return $years;
    }
}

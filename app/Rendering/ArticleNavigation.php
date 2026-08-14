<?php

declare(strict_types=1);

namespace Katakata\Rendering;

use Katakata\Content\Collection;
use Katakata\Content\Post;

final class ArticleNavigation
{
    /** @return array{newer:?Post,older:?Post} */
    public function for(Post $current, Collection $posts): array
    {
        $ordered = array_values(array_filter($posts->all(), static fn (Post $post): bool => $post->isPublished()));
        usort($ordered, static fn (Post $left, Post $right): int => $right->date <=> $left->date);
        foreach ($ordered as $index => $post) {
            if ($post->slug === $current->slug) {
                return ['newer' => $ordered[$index - 1] ?? null, 'older' => $ordered[$index + 1] ?? null];
            }
        }
        return ['newer' => null, 'older' => null];
    }
}

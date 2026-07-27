<?php

declare(strict_types=1);

namespace Katakata\Rendering;

use Katakata\Content\Collection;
use Katakata\Content\Post;

final class AuthorArchive
{
    /**
     * @param Collection<Post> $posts
     * @return array<int, Post>
     */
    public function posts(Collection $posts, string $authorSlug): array
    {
        return array_values(array_filter(
            $posts->all(),
            static fn (Post $post): bool => $post->isPublished() && $post->author === $authorSlug,
        ));
    }
}

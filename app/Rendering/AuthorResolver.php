<?php

declare(strict_types=1);

namespace Katakata\Rendering;

use Katakata\Content\Author;
use Katakata\Content\Post;
use Katakata\Content\Repository;

final class AuthorResolver
{
    public function forPost(Post $post, Repository $repository): ?Author
    {
        return $post->author === null ? null : $repository->findAuthor($post->author);
    }
}

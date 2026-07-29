<?php

declare(strict_types=1);

namespace Katakata\Distribution;

use Katakata\Content\Post;

interface Adapter
{
    public function channel(): string;

    /** @return array<string, mixed> */
    public function distribute(Post $post): array;
}

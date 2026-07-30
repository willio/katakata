<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use DateTimeImmutable;
use Katakata\Content\Collection;
use Katakata\Content\Post;
use Katakata\Rendering\Home;
use PHPUnit\Framework\TestCase;

final class HomeTest extends TestCase
{
    public function testItReturnsOnlyTheLatestPublishedPosts(): void
    {
        $posts = new Collection([
            $this->post('older', '2025-12-31', 'published'),
            $this->post('draft', '2027-01-01', 'draft'),
            $this->post('newest', '2026-07-30', 'published'),
            $this->post('middle', '2026-01-15', 'published'),
        ]);

        self::assertSame(
            ['newest', 'middle'],
            array_map(
                static fn (Post $post): string => $post->slug,
                (new Home())->latest($posts, 2),
            ),
        );
    }

    public function testItReturnsAnEmptyListForAZeroLimit(): void
    {
        self::assertSame([], (new Home())->latest(new Collection([
            $this->post('published', '2026-07-30', 'published'),
        ]), 0));
    }

    private function post(string $slug, string $date, string $status): Post
    {
        return new Post(
            slug: $slug,
            title: ucfirst($slug),
            date: new DateTimeImmutable($date),
            author: null,
            tags: [],
            excerpt: null,
            status: $status,
            body: '',
            meta: [],
            path: "/tmp/{$slug}.md",
        );
    }
}

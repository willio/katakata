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

    public function testItPreparesTheEditorialHomepageGroupsAcrossYearBoundaries(): void
    {
        $posts = new Collection([
            $this->post('older-edition', '2025-12-31', 'published'),
            $this->post('may', '2026-05-02', 'published'),
            $this->post('june-two', '2026-06-08', 'published'),
            $this->post('june-one', '2026-06-22', 'published'),
            $this->post('recent-six', '2026-07-03', 'published'),
            $this->post('recent-five', '2026-07-12', 'published'),
            $this->post('recent-four', '2026-07-21', 'published'),
            $this->post('recent-three', '2026-07-29', 'published'),
            $this->post('recent-two', '2026-08-04', 'published'),
            $this->post('recent-one', '2026-08-08', 'published'),
            $this->post('lead', '2026-08-10', 'published'),
        ]);

        $layout = (new Home())->layout($posts);

        self::assertSame('lead', $layout['lead']?->slug);
        self::assertSame(
            ['recent-one', 'recent-two', 'recent-three', 'recent-four', 'recent-five', 'recent-six'],
            array_map(static fn (Post $post): string => $post->slug, $layout['recent']),
        );
        self::assertSame(['2026-06', '2026-05'], array_keys($layout['earlierThisYear']));
        self::assertSame(
            ['june-one', 'june-two'],
            array_map(static fn (Post $post): string => $post->slug, $layout['earlierThisYear']['2026-06']),
        );
        self::assertSame('2025', $layout['archiveYear']);
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

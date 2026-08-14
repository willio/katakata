<?php

declare(strict_types=1);
namespace Katakata\Tests\Unit;

use DateTimeImmutable;
use Katakata\Content\Collection;
use Katakata\Content\Post;
use Katakata\Rendering\Archive;
use PHPUnit\Framework\TestCase;

final class ArchiveTest extends TestCase
{
    public function testItGroupsPublishedPostsByYearInRepositoryOrder(): void
    {
        $posts = new Collection([
            $this->post('new', '2026-07-27'),
            $this->post('hidden', '2026-06-01', 'draft'),
            $this->post('older', '2025-12-31'),
        ]);

        $years = (new Archive())->years($posts);

        self::assertSame([2026, 2025], array_keys($years));
        self::assertSame(['new'], array_map(
            static fn (Post $post): string => $post->slug,
            $years['2026'],
        ));
        self::assertSame(['older'], array_map(
            static fn (Post $post): string => $post->slug,
            $years['2025'],
        ));
    }

    public function testItFiltersPublishedPostsAcrossEditorialFields(): void
    {
        $posts = new Collection([
            $this->post('calm-software', '2026-07-27', title: 'What Calm Software Means Here', body: 'Quiet systems.'),
            $this->post('hello-world', '2026-01-15', title: 'Hello, World', tags: ['introduction']),
            $this->post('private-note', '2026-01-01', 'draft', title: 'Calm draft'),
        ]);

        $years = (new Archive())->years($posts, 'calm');

        self::assertSame([2026], array_keys($years));
        self::assertSame(['calm-software'], array_map(
            static fn (Post $post): string => $post->slug,
            $years['2026'],
        ));
    }

    public function testItFiltersAnArchiveToOneEditorialMonth(): void
    {
        $posts = new Collection([
            $this->post('june', '2018-06-27'),
            $this->post('may', '2018-05-31'),
            $this->post('older', '2017-06-27'),
        ]);

        $years = (new Archive())->years($posts, '', '2018', '06');

        self::assertSame([2018], array_keys($years));
        self::assertSame(['june'], array_map(
            static fn (Post $post): string => $post->slug,
            $years['2018'],
        ));
    }

    private function post(
        string $slug,
        string $date,
        string $status = 'published',
        ?string $title = null,
        array $tags = [],
        string $body = '',
    ): Post {
        return new Post(
            slug: $slug,
            title: $title ?? ucfirst($slug),
            date: new DateTimeImmutable($date),
            author: null,
            tags: $tags,
            excerpt: null,
            status: $status,
            body: $body,
            meta: [],
            path: "/content/{$slug}.md",
        );
    }
}

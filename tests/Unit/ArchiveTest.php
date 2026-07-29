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

    private function post(string $slug, string $date, string $status = 'published'): Post
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
            path: "/content/{$slug}.md",
        );
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use DateTimeImmutable;
use Katakata\Content\Collection;
use Katakata\Content\Post;
use Katakata\Rendering\AuthorArchive;
use PHPUnit\Framework\TestCase;

final class AuthorArchiveTest extends TestCase
{
    public function testItReturnsOnlyPublishedPostsForTheRequestedAuthor(): void
    {
        $posts = (new AuthorArchive())->posts(new Collection([
            $this->post('mine', 'will', 'published'),
            $this->post('hidden', 'will', 'draft'),
            $this->post('theirs', 'nat', 'published'),
        ]), 'will');

        self::assertSame(['mine'], array_map(
            static fn (Post $post): string => $post->slug,
            $posts,
        ));
    }

    private function post(string $slug, string $author, string $status): Post
    {
        return new Post(
            slug: $slug,
            title: ucfirst($slug),
            date: new DateTimeImmutable('2026-07-28'),
            author: $author,
            tags: [],
            excerpt: null,
            status: $status,
            body: '',
            meta: [],
            path: "/content/{$slug}.md",
        );
    }
}

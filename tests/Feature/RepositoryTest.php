<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use Katakata\Content\Repository;
use PHPUnit\Framework\TestCase;

final class RepositoryTest extends TestCase
{
    private function repository(): Repository
    {
        $base = dirname(__DIR__) . '/Fixtures/content';

        return new Repository(
            $base . '/posts',
            $base . '/drafts',
            $base . '/authors',
            $base . '/assets',
        );
    }

    public function test_it_discovers_and_parses_valid_posts_newest_first(): void
    {
        $posts = $this->repository()->posts();

        $this->assertCount(2, $posts);
        $this->assertSame(
            ['first-post', 'second-post'],
            array_map(static fn ($post) => $post->slug, $posts->all()),
        );
    }

    public function test_it_records_an_error_for_a_post_with_an_invalid_filename(): void
    {
        $repository = $this->repository();
        $repository->posts();

        $this->assertNotEmpty($repository->errors());
        $this->assertStringContainsString('broken.md', $repository->errors()[0]);
    }

    public function test_it_finds_a_post_by_slug_and_exposes_its_fields(): void
    {
        $post = $this->repository()->findPost('first-post');

        $this->assertNotNull($post);
        $this->assertSame('First Post', $post->title);
        $this->assertSame(['a', 'b'], $post->tags);
        $this->assertSame('2026-01-10', $post->date->format('Y-m-d'));
        $this->assertTrue($post->isPublished());
        $this->assertSame('/2026/01/first-post', $post->url());
    }

    public function test_it_returns_null_for_an_unknown_post(): void
    {
        $this->assertNull($this->repository()->findPost('does-not-exist'));
    }

    public function test_it_parses_drafts(): void
    {
        $drafts = $this->repository()->drafts();

        $this->assertCount(1, $drafts);
        $this->assertSame('Draft One', $drafts->first()->title);
        $this->assertSame('draft-one', $drafts->first()->slug);
    }

    public function test_it_parses_authors(): void
    {
        $author = $this->repository()->findAuthor('test-author');

        $this->assertNotNull($author);
        $this->assertSame('Test Author', $author->name);
        $this->assertSame('/assets/test.png', $author->avatar);
        $this->assertSame(['https://example.com/test-author'], $author->social);
    }

    public function test_it_discovers_assets(): void
    {
        $assets = $this->repository()->assets();

        $this->assertCount(1, $assets);
        $this->assertSame('image/png', $assets->first()->mimeType);
        $this->assertGreaterThan(0, $assets->first()->bytes);
    }

    public function test_refresh_clears_cached_content_and_errors(): void
    {
        $repository = $this->repository();
        $repository->posts();

        $this->assertNotEmpty($repository->errors());

        $repository->refresh();

        $this->assertSame([], $repository->errors());
    }
}

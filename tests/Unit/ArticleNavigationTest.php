<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use DateTimeImmutable;
use Katakata\Content\Collection;
use Katakata\Content\Post;
use Katakata\Rendering\ArticleNavigation;
use PHPUnit\Framework\TestCase;

final class ArticleNavigationTest extends TestCase
{
    public function testItFindsTheNewerAndOlderPublishedArticles(): void
    {
        if (!class_exists(ArticleNavigation::class)) {
            self::fail('Article navigation renderer is missing.');
        }

        $posts = new Collection([
            $this->post('newer', '2026-08-03'),
            $this->post('current', '2026-08-02'),
            $this->post('older', '2026-08-01'),
            $this->post('draft', '2026-08-04', 'draft'),
        ]);

        $navigation = (new ArticleNavigation())->for($this->post('current', '2026-08-02'), $posts);

        self::assertSame('newer', $navigation['newer']?->slug);
        self::assertSame('older', $navigation['older']?->slug);
    }

    private function post(string $slug, string $date, string $status = 'published'): Post
    {
        return new Post($slug, ucfirst($slug), new DateTimeImmutable($date), null, [], null, $status, '', [], "/tmp/{$slug}.md");
    }
}

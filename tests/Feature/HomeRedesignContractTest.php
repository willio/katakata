<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use DateTimeImmutable;
use Katakata\Content\Author;
use Katakata\Content\Post;
use Katakata\View;
use PHPUnit\Framework\TestCase;

final class HomeRedesignContractTest extends TestCase
{
    public function testHomepageKeepsFeaturedLatestAndCompactRecentRows(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/home.php');
        $css = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/home-redesign.css');
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');

        self::assertIsString($view);
        self::assertIsString($css);
        self::assertIsString($routes);
        self::assertStringContainsString('/assets/css/home-redesign.css', $view);
        self::assertStringContainsString('class="home-eyebrow">Latest', $view);
        self::assertStringContainsString('id="recent-writing">Recent', $view);
        self::assertStringContainsString('class="home-index-date"', $view);
        self::assertStringContainsString('class="home-lead-byline"', $view);
        self::assertStringContainsString('id="earlier-this-year">Earlier This Year', $view);
        self::assertStringContainsString('class="home-month-titles"', $view);
        self::assertStringContainsString('class="home-title-separator" aria-hidden="true">·</span>', $view);
        self::assertStringContainsString('Search the archive', $view);
        self::assertStringContainsString('Earlier editions →', $view);
        self::assertStringContainsString('grid-template-columns: 6.4rem minmax(0, 1fr)', $css);
        self::assertStringContainsString('grid-template-columns: 3rem minmax(0, 1fr)', $css);
        self::assertStringContainsString('border-top: 0', $css);
        self::assertStringContainsString('@media (max-width: 32rem)', $css);
        self::assertStringContainsString('grid-template-columns: 1fr', $css);
        self::assertStringContainsString('->layout($repository->posts())', $routes);
    }

    public function testHomepageRendersLeadSixRecentPostsAndMonthlyEarlierTitles(): void
    {
        $posts = [
            $this->post('lead', 'Lead Story', '2026-08-10', 'will'),
            $this->post('recent-1', 'Recent One', '2026-08-08'),
            $this->post('recent-2', 'Recent Two', '2026-08-04'),
            $this->post('recent-3', 'Recent Three', '2026-07-29'),
            $this->post('recent-4', 'Recent Four', '2026-07-21'),
            $this->post('recent-5', 'Recent Five', '2026-07-12'),
            $this->post('recent-6', 'Recent Six', '2026-07-03'),
            $this->post('june-one', 'June One', '2026-06-22'),
            $this->post('june-two', 'June Two', '2026-06-08'),
            $this->post('may-one', 'May One', '2026-05-19'),
            $this->post('older-edition', 'Older Edition', '2025-12-30'),
        ];

        $html = (new View(dirname(__DIR__, 2) . '/resources/views'))->render('home', [
            'name' => 'Katakata',
            'tagline' => '',
            'siteUrl' => 'https://katakata.test',
            'lead' => $posts[0],
            'leadAuthor' => new Author('will', 'Will', null, null, [], ''),
            'recent' => array_slice($posts, 1, 6),
            'earlierThisYear' => [
                '2026-06' => array_slice($posts, 7, 2),
                '2026-05' => array_slice($posts, 9, 1),
            ],
            'archiveYear' => '2025',
        ]);

        self::assertStringContainsString('August 10, 2026</time>, by', $html);
        self::assertStringContainsString('/authors/will">Will</a>', $html);
        self::assertSame(6, substr_count($html, 'class="home-index-title"'));
        self::assertStringContainsString('<span class="home-month-label">Jun</span>', $html);
        self::assertStringContainsString('June One</a>', $html);
        self::assertStringContainsString('June Two</a>', $html);
        self::assertStringContainsString('<span class="home-month-label">May</span>', $html);
        self::assertStringContainsString('/archive#year-2025">Earlier editions →</a>', $html);
        self::assertStringNotContainsString('Older Edition</a>', $html);
    }

    public function testHomepageDoesNotBecomeAnApplicationCardGrid(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/home.php');
        $css = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/home-redesign.css');

        self::assertIsString($view);
        self::assertIsString($css);
        self::assertStringNotContainsString('home-card', $view);
        self::assertStringNotContainsString('box-shadow', $css);
        self::assertStringNotContainsString('border-radius', $css);
    }

    public function testHomepageUsesThePublicationNameWhenTheLeadHasNoAuthor(): void
    {
        $html = (new View(dirname(__DIR__, 2) . '/resources/views'))->render('home', [
            'name' => 'Katakata',
            'tagline' => '',
            'siteUrl' => 'https://katakata.test',
            'lead' => $this->post('lead', 'Lead Story', '2026-08-10'),
            'leadAuthor' => null,
            'recent' => [],
            'earlierThisYear' => [],
            'archiveYear' => null,
        ]);

        self::assertStringContainsString('August 10, 2026</time>, by', $html);
        self::assertStringContainsString('<span class="home-lead-byline">Katakata</span>', $html);
    }

    private function post(string $slug, string $title, string $date, ?string $author = null): Post
    {
        return new Post(
            slug: $slug,
            title: $title,
            date: new DateTimeImmutable($date),
            author: $author,
            tags: [],
            excerpt: null,
            status: 'published',
            body: '',
            meta: [],
            path: "/tmp/{$slug}.md",
        );
    }
}

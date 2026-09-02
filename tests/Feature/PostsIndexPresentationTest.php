<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use DateTimeImmutable;
use Katakata\Content\Draft;
use Katakata\Content\Post;
use Katakata\View;
use PHPUnit\Framework\TestCase;

final class PostsIndexPresentationTest extends TestCase
{
    public function testDraftFilterShowsAnUnnumberedLinkedDraftIndexAndItsCount(): void
    {
        $html = (new View(dirname(__DIR__, 2) . '/resources/views'))->render('posts', [
            'siteName' => 'Katakata',
            'status' => 'drafts',
            'drafts' => [
                new Draft('first-draft', 'First Draft', new DateTimeImmutable('2026-08-02'), '', [], ''),
                new Draft('scheduled-draft', 'Scheduled Draft', new DateTimeImmutable('2026-08-01'), '', [
                    'status' => 'scheduled',
                    'publish_at' => '2026-08-03T09:00:00+00:00',
                ], ''),
            ],
            'posts' => [],
        ]);

        self::assertStringContainsString('<a href="/posts?status=drafts" aria-current="page">Drafts (1)</a>', $html);
        self::assertStringContainsString('<ul class="posts-index">', $html);
        self::assertStringNotContainsString('<ol class="posts-index">', $html);
        self::assertStringContainsString('<a class="posts-index-title" href="/editor/drafts/first-draft">First Draft</a>', $html);
        self::assertStringContainsString('>Edit</a>', $html);
    }

    public function testScheduledFilterClassifiesDraftsByStatusAndPublishAt(): void
    {
        $html = (new View(dirname(__DIR__, 2) . '/resources/views'))->render('posts', [
            'siteName' => 'Katakata',
            'status' => 'all',
            'drafts' => [
                new Draft('plain-draft', 'Plain Draft', new DateTimeImmutable('2026-08-02'), '', [], ''),
                new Draft('scheduled-draft', 'Scheduled Draft', new DateTimeImmutable('2026-08-01'), '', [
                    'status' => 'scheduled',
                    'publish_at' => '2026-08-03T09:00:00+00:00',
                ], ''),
            ],
            'posts' => [],
        ]);

        self::assertStringContainsString('>Scheduled (1)</a>', $html);
        self::assertStringContainsString('>Drafts (1)</a>', $html);
        self::assertStringContainsString('<span class="posts-status-pill">Scheduled</span>', $html);
    }

    public function testPostsIndexSuppressesListMarkers(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/site.css');

        self::assertIsString($css);
        self::assertStringContainsString('.posts-index { margin: 0; padding: 0; list-style: none; }', $css);
    }

    public function testPublishedPostTitleIsItsPublicLink(): void
    {
        $html = (new View(dirname(__DIR__, 2) . '/resources/views'))->render('posts', [
            'siteName' => 'Katakata',
            'status' => 'published',
            'drafts' => [],
            'posts' => [
                new Post('published-post', 'Published Post', new DateTimeImmutable('2026-08-02'), 'Author', [], null, 'published', '', [], ''),
            ],
        ]);

        self::assertStringContainsString('<a class="posts-index-title" href="/2026/08/published-post">Published Post</a>', $html);
        self::assertStringNotContainsString('>View</a>', $html);
    }

    public function testFilterLinksPreserveTheActiveSearch(): void
    {
        $html = (new View(dirname(__DIR__, 2) . '/resources/views'))->render('posts', [
            'siteName' => 'Katakata',
            'status' => 'all',
            'search' => 'kitchen sink',
            'drafts' => [],
            'posts' => [],
        ]);

        self::assertStringContainsString('href="/posts?status=published&q=kitchen%20sink"', $html);
        self::assertStringContainsString('No posts match “kitchen sink”.', $html);
    }

    public function testActiveSearchRendersAFlatNumberedResultList(): void
    {
        $html = (new View(dirname(__DIR__, 2) . '/resources/views'))->render('posts', [
            'siteName' => 'Katakata',
            'status' => 'all',
            'search' => 'first',
            'drafts' => [
                new Draft('first-draft', 'First Draft', new DateTimeImmutable('2026-08-02'), '', [], ''),
                new Draft('other', 'Unrelated', new DateTimeImmutable('2026-08-01'), '', [], ''),
            ],
            'posts' => [],
        ]);

        self::assertStringContainsString('1 result for “first”', $html);
        self::assertStringContainsString('<ul class="posts-index">', $html);
        self::assertStringNotContainsString('posts-year', $html);
        self::assertStringNotContainsString('Unrelated', $html);
    }

    public function testRowsCarryStatusPillsAndTrashRowsHideTheActor(): void
    {
        $html = (new View(dirname(__DIR__, 2) . '/resources/views'))->render('posts', [
            'siteName' => 'Katakata',
            'status' => 'all',
            'drafts' => [
                new Draft('first-draft', 'First Draft', new DateTimeImmutable('2026-08-02'), '', [], ''),
            ],
            'posts' => [],
        ]);

        self::assertStringContainsString('<span class="posts-status-pill">Draft</span>', $html);
    }

    public function testSearchFieldFollowsTheFieldContractWithAClearControl(): void
    {
        $html = (new View(dirname(__DIR__, 2) . '/resources/views'))->render('posts', [
            'siteName' => 'Katakata',
            'status' => 'all',
            'drafts' => [],
            'posts' => [],
        ]);

        self::assertStringContainsString('data-field-clear="posts-search-query"', $html);
        self::assertStringContainsString('/assets/js/fields.js', $html);
    }
}

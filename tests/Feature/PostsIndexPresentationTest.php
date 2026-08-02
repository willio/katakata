<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use DateTimeImmutable;
use Katakata\Content\Draft;
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
                new Draft('scheduled-draft', 'Scheduled Draft', new DateTimeImmutable('2026-08-01'), '', ['scheduled_at' => '2026-08-03T09:00:00+00:00'], ''),
            ],
            'posts' => [],
        ]);

        self::assertStringContainsString('<a href="/posts?status=drafts" aria-current="page">Draft (1)</a>', $html);
        self::assertStringContainsString('<ul class="posts-index">', $html);
        self::assertStringNotContainsString('<ol class="posts-index">', $html);
        self::assertStringContainsString('<strong><a href="/editor/drafts/first-draft">First Draft</a></strong>', $html);
        self::assertStringNotContainsString('>Edit</a>', $html);
    }

    public function testPostsIndexSuppressesListMarkers(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/site.css');

        self::assertIsString($css);
        self::assertStringContainsString('.posts-index { margin: 0; padding: 0; list-style: none; }', $css);
    }
}

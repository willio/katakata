<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class PostsBoundaryDesignContractTest extends TestCase
{
    public function testPostsKeepTheTitleAsTheOnlyRowLinkAndSeparateScheduledDrafts(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/posts.php');

        self::assertIsString($view);
        self::assertStringContainsString("'drafts' => 'Drafts'", $view);
        self::assertStringContainsString("'scheduled' => 'Scheduled'", $view);
        self::assertStringContainsString('$scheduledAt === \'\'', $view);
        self::assertStringContainsString("'href' => '/editor/drafts/'", $view);
        self::assertStringContainsString('class="posts-index-title"', $view);
        self::assertStringNotContainsString('posts-index-actions', $view);
        self::assertStringNotContainsString('>Edit<', $view);
        self::assertStringNotContainsString('>View<', $view);
    }

    public function testPostsUseMarkerFreeRowsAndWholeResponsiveFilters(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/posts.php');
        $css = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/posts.css');

        self::assertIsString($view);
        self::assertIsString($css);
        self::assertStringContainsString('/assets/css/posts.css', $view);
        self::assertStringContainsString('list-style: none', $css);
        self::assertStringContainsString('grid-template-columns: minmax(0, 1fr) auto', $css);
        self::assertStringContainsString('overflow-x: auto', $css);
        self::assertStringContainsString('white-space: nowrap', $css);
        self::assertStringContainsString('@media (max-width: 20rem)', $css);
        self::assertStringContainsString('grid-template-columns: minmax(0, 1fr)', $css);
    }
}

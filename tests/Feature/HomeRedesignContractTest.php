<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class HomeRedesignContractTest extends TestCase
{
    public function testHomepageKeepsFeaturedLatestAndCompactRecentRows(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/home.php');
        $css = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/home-redesign.css');

        self::assertIsString($view);
        self::assertIsString($css);
        self::assertStringContainsString('/assets/css/home-redesign.css', $view);
        self::assertStringContainsString('class="home-eyebrow">Latest', $view);
        self::assertStringContainsString('id="recent-writing">Recent', $view);
        self::assertStringContainsString('class="home-index-date"', $view);
        self::assertStringContainsString('class="home-index-excerpt"', $view);
        self::assertStringContainsString('Search the archive', $view);
        self::assertStringContainsString('Earlier editions →', $view);
        self::assertStringContainsString('grid-template-columns: 6.4rem minmax(0, 1fr)', $css);
        self::assertStringContainsString('@media (max-width: 32rem)', $css);
        self::assertStringContainsString('grid-template-columns: 1fr', $css);
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
}

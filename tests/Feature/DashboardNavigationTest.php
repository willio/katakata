<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class DashboardNavigationTest extends TestCase
{
    public function test_dashboard_uses_linked_attention_cards_and_no_seo_card(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/dashboard.php');

        self::assertIsString($view);
        self::assertStringContainsString("href=\"<?= e(\$card['href']) ?>\"", $view);
        self::assertStringContainsString("<?= e(\$card['label']) ?>", $view);
        self::assertStringNotContainsString('SEO checks clear', $view);
        self::assertStringNotContainsString('SEO issues', $view);
    }

    public function test_dashboard_destinations_are_registered_before_article_route(): void
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $router = require dirname(__DIR__, 2) . '/bootstrap/routes.php';
        $routes = array_values($router->all());

        foreach ([
            ['method' => 'GET', 'path' => '/posts'],
            ['method' => 'GET', 'path' => '/analytics'],
        ] as $route) {
            self::assertContains($route, $routes);
        }

        $article = array_search(
            ['method' => 'GET', 'path' => '/{year}/{month}/{slug}'],
            $routes,
            true,
        );
        self::assertIsInt($article);

        foreach ([
            ['method' => 'GET', 'path' => '/posts'],
            ['method' => 'GET', 'path' => '/analytics'],
        ] as $route) {
            $index = array_search($route, $routes, true);
            self::assertIsInt($index);
            self::assertLessThan($article, $index);
        }
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use Katakata\Http\Request;
use PHPUnit\Framework\TestCase;

final class DashboardSettingsRoutesTest extends TestCase
{
    public function testSettingsRoutesAreComposedExactlyOnce(): void
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $router = require dirname(__DIR__, 2) . '/bootstrap/routes.php';
        $routes = $router->all();

        foreach (['GET', 'POST'] as $method) {
            $matches = array_filter(
                $routes,
                static fn (array $route): bool => $route === [
                    'method' => $method,
                    'path' => '/dashboard/settings',
                ],
            );
            self::assertCount(1, $matches);
        }
    }

    public function testGuestSettingsRequestsRedirectToLogin(): void
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $router = require dirname(__DIR__, 2) . '/bootstrap/routes.php';

        foreach ([
            new Request('GET', '/dashboard/settings'),
            new Request('POST', '/dashboard/settings'),
        ] as $request) {
            $response = $router->dispatch($request);
            self::assertSame(302, $response->status);
            self::assertSame('/login', $response->headers['Location']);
        }
    }

    public function testSettingsRouteIsBeforeTheGreedyArticleRoute(): void
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $router = require dirname(__DIR__, 2) . '/bootstrap/routes.php';
        $routes = array_values($router->all());

        $settings = array_search(
            ['method' => 'GET', 'path' => '/dashboard/settings'],
            $routes,
            true,
        );
        $article = array_search(
            ['method' => 'GET', 'path' => '/{year}/{month}/{slug}'],
            $routes,
            true,
        );

        self::assertIsInt($settings);
        self::assertIsInt($article);
        self::assertLessThan($article, $settings);
    }
}

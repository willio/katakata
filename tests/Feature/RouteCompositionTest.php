<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class RouteCompositionTest extends TestCase
{
    public function test_front_controller_composes_the_complete_application_route_set(): void
    {
        $server = $_SERVER;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/route-composition-probe';

        ob_start();
        require dirname(__DIR__, 2) . '/public/index.php';
        ob_end_clean();

        $_SERVER = $server;
        $routes = $router->all();

        foreach ([
            ['method' => 'GET', 'path' => '/'],
            ['method' => 'GET', 'path' => '/login'],
            ['method' => 'GET', 'path' => '/dashboard'],
            ['method' => 'GET', 'path' => '/editor/new'],
            ['method' => 'GET', 'path' => '/dashboard/mail'],
            ['method' => 'POST', 'path' => '/{year}/{month}/{slug}/discussion'],
        ] as $route) {
            $this->assertContains($route, $routes);
        }

        $articleRoutes = array_filter(
            $routes,
            static fn (array $route): bool => $route === [
                'method' => 'GET',
                'path' => '/{year}/{month}/{slug}',
            ],
        );

        $this->assertCount(1, $articleRoutes);
    }
}

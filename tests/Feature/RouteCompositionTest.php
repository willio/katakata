<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use Katakata\Http\Request;
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
            ['method' => 'GET', 'path' => '/healthz'],
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

    public function test_invalid_discussion_csrf_redirect_keeps_the_status_in_the_query_string(): void
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $router = require dirname(__DIR__, 2) . '/bootstrap/routes.php';

        $response = $router->dispatch(new Request(
            'POST',
            '/2026/01/hello-world/discussion',
            body: ['csrf' => 'invalid'],
        ));

        $this->assertSame(303, $response->status);
        $this->assertSame('/2026/01/hello-world?comment=expired#discussion', $response->headers['Location']);
    }

    public function test_dashboard_route_uses_the_dashboard_service_and_view_contracts(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');

        $this->assertIsString($routes);
        $this->assertStringContainsString("'siteName' =>", $routes);
        $this->assertStringContainsString("'recentDrafts' =>", $routes);
        $this->assertStringContainsString("'latestPosts' =>", $routes);
        $this->assertStringContainsString("'recentVisits' => \$dashboardAnalytics->recent(\$analytics)", $routes);
        $this->assertStringContainsString('DashboardBuzz::class)->recent()', $routes);
        $this->assertStringContainsString("'cards' => \$app->make(DashboardAttention::class)->cards()", $routes);
        $this->assertStringNotContainsString('SeoChecker::class)->check()', $routes);
    }
}

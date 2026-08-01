<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use Katakata\Http\Request;
use PHPUnit\Framework\TestCase;

final class MailCampaignRoutesTest extends TestCase
{
    public function test_campaign_routes_are_composed_once_before_the_article_route(): void
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $router = require dirname(__DIR__, 2) . '/bootstrap/routes.php';
        $routes = $router->all();

        foreach ([
            ['method' => 'GET', 'path' => '/mail'],
            ['method' => 'GET', 'path' => '/mail/confirm'],
            ['method' => 'POST', 'path' => '/mail/confirm'],
            ['method' => 'GET', 'path' => '/mail/campaigns'],
            ['method' => 'GET', 'path' => '/mail/campaign/{id}'],
            ['method' => 'POST', 'path' => '/mail/campaign/{id}/retry'],
        ] as $expected) {
            self::assertSame(1, count(array_filter(
                $routes,
                static fn (array $route): bool => $route === $expected,
            )), sprintf('%s %s must be registered exactly once.', $expected['method'], $expected['path']));
        }

        $articleIndex = array_search(
            ['method' => 'GET', 'path' => '/{year}/{month}/{slug}'],
            $routes,
            true,
        );
        $campaignIndex = array_search(
            ['method' => 'GET', 'path' => '/mail/campaign/{id}'],
            $routes,
            true,
        );

        self::assertIsInt($articleIndex);
        self::assertIsInt($campaignIndex);
        self::assertLessThan($articleIndex, $campaignIndex);
    }

    public function test_campaign_pages_require_authentication(): void
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $router = require dirname(__DIR__, 2) . '/bootstrap/routes.php';

        foreach (['/mail', '/mail/confirm', '/mail/campaigns'] as $path) {
            $response = $router->dispatch(new Request('GET', $path));

            self::assertSame(302, $response->status);
            self::assertSame('/login', $response->headers['Location'] ?? null);
        }
    }

    public function test_campaign_views_are_present_and_use_the_dashboard_shell(): void
    {
        foreach ([
            'mail.php',
            'mail-confirm.php',
            'mail-campaigns.php',
            'mail-campaign.php',
        ] as $view) {
            $path = dirname(__DIR__, 2) . '/resources/views/' . $view;
            self::assertFileExists($path);

            $source = file_get_contents($path);
            self::assertIsString($source);
            self::assertStringContainsString('dashboard-page', $source);
            self::assertStringContainsString('href="/mail', $source);
        }
    }
}

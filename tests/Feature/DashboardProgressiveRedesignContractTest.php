<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class DashboardProgressiveRedesignContractTest extends TestCase
{
    public function testDashboardDefinesFirstLoginAndMatureStates(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/dashboard.php');

        self::assertIsString($view);
        self::assertStringContainsString('$isFirstLogin =', $view);
        self::assertStringContainsString('Your publication is ready.', $view);
        self::assertStringContainsString('Create your first post', $view);
        self::assertStringContainsString('Getting started', $view);
        self::assertStringContainsString('dashboard-page--first-login', $view);
        self::assertStringContainsString('dashboard-page--mature', $view);
    }

    public function testEmptyModulesAreNotRenderedInMatureState(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/dashboard.php');

        self::assertIsString($view);
        self::assertStringContainsString('if ($analytics !== null && $recentVisits !== [])', $view);
        self::assertStringContainsString('if ($buzz !== null && $buzz !== [])', $view);
        self::assertStringNotContainsString('Analytics is unavailable.', $view);
        self::assertStringNotContainsString('No visits recorded yet.', $view);
        self::assertStringNotContainsString('No synced replies yet.', $view);
        self::assertStringNotContainsString('Visitor map', $view);
        self::assertStringNotContainsString('Top posts', $view);
    }

    public function testDashboardUsesSelectiveCardsAndSharedRadiusToken(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/dashboard.php');
        $boundary = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/boundary.css');
        $site = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/site.css');
        $css = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/dashboard-redesign.css');

        self::assertIsString($view);
        self::assertIsString($boundary);
        self::assertIsString($site);
        self::assertIsString($css);
        self::assertStringContainsString('/assets/css/dashboard-redesign.css', $view);
        self::assertStringContainsString('--radius-control: 16px', $site);
        self::assertStringContainsString('.dashboard-stat-card', $boundary);
        self::assertStringContainsString('.dashboard-stat-card', $css);
        self::assertStringNotContainsString('--workspace-radius', $css);
        self::assertStringNotContainsString('border-radius: var(--workspace-radius)', $css);
        self::assertStringContainsString('@media (max-width: 20rem)', $css);
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class DashboardBoundaryDesignContractTest extends TestCase
{
    public function testDashboardKeepsFourLinkedSummaryCards(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/dashboard.php');

        self::assertIsString($view);
        self::assertStringContainsString('class="dashboard-stats"', $view);
        self::assertStringContainsString('class="dashboard-stat-card"', $view);
        self::assertStringContainsString("href=\"<?= e(\$card['href']) ?>\"", $view);
    }

    public function testDashboardLimitsVisitsAndOffersAnalyticsDestination(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/dashboard.php');

        self::assertIsString($view);
        self::assertStringContainsString('array_slice($recentVisits, 0, 5)', $view);
        self::assertStringContainsString('href="/analytics">View analytics</a>', $view);
    }

    public function testDashboardOmitsUnavailableBuzzAndOwnerEmail(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/dashboard.php');

        self::assertIsString($view);
        self::assertStringContainsString('if ($buzz !== null)', $view);
        self::assertStringNotContainsString("$user['email']", $view);
        self::assertStringNotContainsString("$user['name']", $view);
    }
}

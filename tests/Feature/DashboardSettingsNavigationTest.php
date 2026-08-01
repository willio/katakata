<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use DateTimeImmutable;
use Katakata\Seo\SeoCheckSummary;
use Katakata\View;
use PHPUnit\Framework\TestCase;

final class DashboardSettingsNavigationTest extends TestCase
{
    public function testDashboardNavigationLinksToTheCanonicalGlobalSettingsPage(): void
    {
        $html = (new View(dirname(__DIR__, 2) . '/resources/views'))->render('dashboard', [
            'user' => ['email' => 'owner@example.test'],
            'siteName' => 'Katakata',
            'recentDrafts' => [],
            'latestPosts' => [],
            'publishedCount' => 0,
            'draftCount' => 0,
            'seo' => new SeoCheckSummary(new DateTimeImmutable('2026-08-01T00:00:00+00:00'), []),
            'analytics' => null,
            'recentVisits' => [],
            'buzz' => null,
            'csrf' => 'test-token',
        ]);

        self::assertStringContainsString('<a href="/dashboard/settings">Settings</a>', $html);
        self::assertStringNotContainsString('<a href="/editor">Settings</a>', $html);
    }

}

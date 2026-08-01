<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

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
            'cards' => [
                ['label' => 'Visits', 'count' => '—', 'detail' => 'Analytics unavailable', 'href' => '/analytics'],
                ['label' => 'Posts', 'count' => 0, 'detail' => 'Published content', 'href' => '/posts'],
                ['label' => 'Drafts', 'count' => 0, 'detail' => 'Work in progress', 'href' => '/posts?status=drafts'],
                ['label' => 'Inbox', 'count' => 0, 'detail' => 'No mail needs attention', 'href' => '/mail'],
            ],
            'analytics' => null,
            'recentVisits' => [],
            'buzz' => null,
            'csrf' => 'test-token',
        ]);

        self::assertStringContainsString('<a href="/dashboard/settings">Settings</a>', $html);
        self::assertStringNotContainsString('<a href="/editor">Settings</a>', $html);
    }
}

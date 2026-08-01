<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Dashboard;

use Katakata\Analytics\AnalyticsStore;
use Katakata\Content\Repository;
use Katakata\Dashboard\DashboardAnalytics;
use Katakata\Dashboard\DashboardAttention;
use PHPUnit\Framework\TestCase;

final class DashboardAttentionTest extends TestCase
{
    public function test_it_builds_the_four_contextual_dashboard_cards(): void
    {
        $root = sys_get_temp_dir() . '/katakata-dashboard-attention-' . bin2hex(random_bytes(4));
        mkdir($root . '/posts', 0775, true);
        mkdir($root . '/drafts', 0775, true);
        mkdir($root . '/authors', 0775, true);
        mkdir($root . '/assets', 0775, true);
        mkdir($root . '/analytics', 0775, true);

        file_put_contents($root . '/drafts/draft-one.md', "---\ntitle: Draft one\n---\nBody\n");
        mkdir($root . '/posts/2026/08', 0775, true);
        file_put_contents($root . '/posts/2026/08/260801_post-one.md', "---\ntitle: Post one\n---\nBody\n");

        $repository = new Repository(
            $root . '/posts',
            $root . '/drafts',
            $root . '/authors',
            $root . '/assets',
        );
        $analytics = new DashboardAnalytics(
            new AnalyticsStore($root . '/analytics/analytics.sqlite'),
            $repository,
        );

        $cards = (new DashboardAttention($repository, $analytics))->cards();

        self::assertSame(['Visits', 'Posts', 'Drafts', 'Inbox'], array_column($cards, 'label'));
        self::assertSame(['/analytics', '/posts', '/posts?status=drafts', '/mail'], array_column($cards, 'href'));
        self::assertSame(1, $cards[1]['count']);
        self::assertSame(1, $cards[2]['count']);
    }
}

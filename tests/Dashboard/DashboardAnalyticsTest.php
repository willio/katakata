<?php

declare(strict_types=1);

namespace Katakata\Tests\Dashboard;

use DateTimeImmutable;
use Katakata\Analytics\AnalyticsStore;
use Katakata\Content\Repository;
use Katakata\Dashboard\DashboardAnalytics;
use PHPUnit\Framework\TestCase;

final class DashboardAnalyticsTest extends TestCase
{
    public function test_it_presents_content_titles_without_exposing_unknown_raw_paths(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
        }

        $root = sys_get_temp_dir() . '/katakata-dashboard-' . bin2hex(random_bytes(6));
        foreach (['posts/2026/07', 'drafts', 'authors', 'assets'] as $path) {
            mkdir($root . '/' . $path, 0775, true);
        }
        file_put_contents($root . '/posts/2026/07/260728_first.md', <<<MD
---
title: First article
date: 2026-07-28
excerpt: This excerpt is intentionally long enough to satisfy the basic metadata check.
---
Body.
MD);
        $repository = new Repository(
            $root . '/posts',
            $root . '/drafts',
            $root . '/authors',
            $root . '/assets',
        );
        $store = new AnalyticsStore($root . '/analytics.sqlite');
        $at = new DateTimeImmutable('2026-07-28T10:00:00Z');
        $store->record('/2026/07/first', null, null, 'visitor-1', $at);
        $store->record('/private-looking-path', null, null, 'visitor-2', $at);

        $service = new DashboardAnalytics($store, $repository);
        $recent = $service->recent($service->summary());

        self::assertSame('Site page', $recent[0]['page']);
        self::assertSame('First article', $recent[1]['page']);

        foreach (glob($root . '/analytics.sqlite*') ?: [] as $databaseFile) {
            unlink($databaseFile);
        }
        unlink($root . '/posts/2026/07/260728_first.md');
        rmdir($root . '/posts/2026/07');
        rmdir($root . '/posts/2026');
        rmdir($root . '/posts');
        rmdir($root . '/drafts');
        rmdir($root . '/authors');
        rmdir($root . '/assets');
        rmdir($root);
    }
}

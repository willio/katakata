<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use DateTimeImmutable;
use Katakata\Analytics\AnalyticsStore;
use Katakata\Analytics\VisitorHasher;
use Katakata\Analytics\VisitRecorder;
use Katakata\Http\Request;
use PDO;
use PHPUnit\Framework\TestCase;

final class AnalyticsTest extends TestCase
{
    private string $root;
    private string $database;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is not available.');
        }

        $this->root = sys_get_temp_dir() . '/katakata-analytics-' . bin2hex(random_bytes(6));
        $this->database = $this->root . '/analytics.sqlite';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->database . '*') ?: [] as $path) {
            @unlink($path);
        }
        if (is_dir($this->root)) {
            rmdir($this->root);
        }
    }

    public function testVisitorHashIsStableWithinOneUtcDayAndRotatesNextDay(): void
    {
        $hasher = new VisitorHasher('test-secret');
        $first = $hasher->hash('203.0.113.1', 'Browser', new DateTimeImmutable('2026-07-28T01:00:00Z'));
        $sameDay = $hasher->hash('203.0.113.1', 'Browser', new DateTimeImmutable('2026-07-28T23:00:00Z'));
        $nextDay = $hasher->hash('203.0.113.1', 'Browser', new DateTimeImmutable('2026-07-29T01:00:00Z'));

        self::assertSame($first, $sameDay);
        self::assertNotSame($first, $nextDay);
        self::assertSame(16, strlen($first));
    }

    public function testStoreBuildsWindowedSummaryAndRecentVisits(): void
    {
        $store = new AnalyticsStore($this->database);
        $now = new DateTimeImmutable('2026-07-28T12:00:00Z');
        $store->record('/today', null, 'ID-JB', 'visitor-a', $now->modify('-1 day'));
        $store->record('/today-again', null, 'ID-JB', 'visitor-a', $now->modify('-1 hour'));
        $store->record('/week', 'https://example.com', 'ID-JK', 'visitor-b', $now->modify('-6 days'));
        $store->record('/previous', null, null, 'visitor-c', $now->modify('-10 days'));
        $store->record('/old', null, null, 'visitor-d', $now->modify('-40 days'));

        $summary = $store->summary($now);

        self::assertSame(2, $summary->visits7d);
        self::assertSame(3, $summary->visits30d);
        self::assertSame(4, $summary->visits365d);
        self::assertSame(4, $summary->visitsAllTime);
        self::assertSame(100.0, $summary->visits7dTrendPct);
        self::assertSame(['ID-JB', 'ID-JK'], array_column($summary->regions, 'region'));
        self::assertSame('/today-again', $summary->recentVisits[0]->path);
    }

    public function testRecorderNeverPersistsRawIpAddress(): void
    {
        $store = new AnalyticsStore($this->database);
        $recorder = new VisitRecorder($store, new VisitorHasher('test-secret'));
        $request = new Request('GET', '/article', server: [
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_USER_AGENT' => 'Test browser',
            'HTTP_REFERER' => 'https://example.com/',
        ]);

        self::assertTrue($recorder->record($request, at: new DateTimeImmutable('2026-07-28T12:00:00Z')));

        $pdo = new PDO('sqlite:' . $this->database);
        $row = $pdo->query('SELECT path, referrer, visitor_hash FROM visits')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('/article', $row['path']);
        self::assertSame('https://example.com/', $row['referrer']);
        self::assertNotSame('203.0.113.10', $row['visitor_hash']);
        self::assertSame(16, strlen((string) $row['visitor_hash']));
    }

    public function testPruneDeletesOnlyRowsOutsideRetentionWindow(): void
    {
        $store = new AnalyticsStore($this->database);
        $now = new DateTimeImmutable('2026-07-28T12:00:00Z');
        $store->record('/expired', null, null, 'old', $now->modify('-401 days'));
        $store->record('/kept', null, null, 'new', $now->modify('-399 days'));

        self::assertSame(1, $store->prune(400, $now));
        self::assertSame(['/kept'], array_map(
            static fn ($visit): string => $visit->path,
            $store->summary($now)->recentVisits,
        ));
    }
}

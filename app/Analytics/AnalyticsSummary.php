<?php

declare(strict_types=1);

namespace Katakata\Analytics;

final readonly class AnalyticsSummary
{
    /**
     * @param array<int, array{region: string, count: int}> $regions
     * @param array<int, RecentVisit> $recentVisits
     */
    public function __construct(
        public int $visits7d,
        public int $visits30d,
        public int $visits365d,
        public int $visitsAllTime,
        public float $visits7dTrendPct,
        public array $regions,
        public array $recentVisits,
    ) {
    }
}

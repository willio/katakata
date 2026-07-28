<?php

declare(strict_types=1);

namespace Katakata\Dashboard;

use Katakata\Analytics\AnalyticsStore;
use Katakata\Analytics\AnalyticsSummary;
use Katakata\Content\Repository;
use Throwable;

final class DashboardAnalytics
{
    public function __construct(
        private readonly AnalyticsStore $analytics,
        private readonly Repository $repository,
    ) {
    }

    public function summary(): ?AnalyticsSummary
    {
        try {
            return $this->analytics->summary();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array{at: \DateTimeImmutable, page: string, referrer: ?string, region: ?string}>
     */
    public function recent(?AnalyticsSummary $summary): array
    {
        if ($summary === null) {
            return [];
        }

        $titles = ['/' => 'Home', '/archive' => 'Archive'];
        foreach ($this->repository->posts() as $post) {
            $titles[$post->url()] = $post->title;
        }

        return array_map(
            static fn ($visit): array => [
                'at' => $visit->at,
                'page' => $titles[$visit->path] ?? 'Site page',
                'referrer' => $visit->referrer,
                'region' => $visit->region,
            ],
            $summary->recentVisits,
        );
    }
}

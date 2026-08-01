<?php

declare(strict_types=1);

namespace Katakata\Dashboard;

use Katakata\Content\Repository;

final class DashboardAttention
{
    public function __construct(
        private readonly Repository $repository,
        private readonly DashboardAnalytics $analytics,
    ) {
    }

    /**
     * @return list<array{label:string,count:int|string,detail:?string,href:string}>
     */
    public function cards(): array
    {
        $summary = $this->analytics->summary();

        return [
            [
                'label' => 'Visits',
                'count' => $summary?->visits7d ?? '—',
                'detail' => $summary === null ? 'Analytics unavailable' : 'Last 7 days',
                'href' => '/analytics',
            ],
            [
                'label' => 'Posts',
                'count' => count($this->repository->posts()->all()),
                'detail' => 'Published content',
                'href' => '/posts',
            ],
            [
                'label' => 'Drafts',
                'count' => count($this->repository->drafts()->all()),
                'detail' => 'Work in progress',
                'href' => '/posts?status=drafts',
            ],
            [
                'label' => 'Inbox',
                'count' => '—',
                'detail' => 'Mail workspace',
                'href' => '/mail',
            ],
        ];
    }
}

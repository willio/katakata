<?php

declare(strict_types=1);

namespace Katakata\Dashboard;

use Katakata\Content\Repository;
use Katakata\Mail\MailAttention;

final class DashboardAttention
{
    public function __construct(
        private readonly Repository $repository,
        private readonly DashboardAnalytics $analytics,
        private readonly MailAttention $mail,
    ) {
    }

    /**
     * @return list<array{label:string,count:int|string,detail:?string,href:string}>
     */
    public function cards(): array
    {
        $summary = $this->analytics->summary();
        $mail = $this->mail->summary();

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
                'count' => $mail['total'],
                'detail' => $mail['detail'],
                'href' => '/mail',
            ],
        ];
    }
}

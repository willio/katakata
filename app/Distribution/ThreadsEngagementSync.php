<?php

declare(strict_types=1);

namespace Katakata\Distribution;

final class ThreadsEngagementSync
{
    public function __construct(
        private readonly ThreadsInsightsApi $api,
        private readonly ThreadsStore $store,
    ) {
    }

    /** @return array{posts: int, failed: int} */
    public function sync(): array
    {
        $posts = 0;
        $failed = 0;
        foreach ($this->store->publications() as $slug => $publication) {
            try {
                $this->store->replaceEngagement($slug, $this->api->insights($publication['media_id']));
                $posts++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        return ['posts' => $posts, 'failed' => $failed];
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Distribution;

final class ThreadsReplySync
{
    public function __construct(
        private readonly ThreadsApi $api,
        private readonly ThreadsStore $store,
    ) {
    }

    /** @return array{posts: int, replies: int, failed: int} */
    public function sync(): array
    {
        $posts = 0;
        $replies = 0;
        $failed = 0;
        foreach ($this->store->publications() as $slug => $publication) {
            try {
                $rows = $this->api->replies($publication['media_id']);
                $this->store->replaceReplies($slug, $rows);
                $posts++;
                $replies += count($rows);
            } catch (\Throwable) {
                $failed++;
            }
        }

        return ['posts' => $posts, 'replies' => $replies, 'failed' => $failed];
    }
}

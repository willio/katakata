<?php

declare(strict_types=1);

namespace Katakata\Tests\Dashboard;

use Katakata\Dashboard\DashboardBuzz;
use Katakata\Distribution\ThreadsStore;
use Katakata\Editorial\AtomicFile;
use PHPUnit\Framework\TestCase;

final class DashboardBuzzTest extends TestCase
{
    public function test_it_distinguishes_disabled_empty_and_cached_reply_states(): void
    {
        $root = sys_get_temp_dir() . '/katakata-dashboard-buzz-' . bin2hex(random_bytes(5));
        mkdir($root, 0775, true);
        $store = new ThreadsStore($root . '/threads.json', new AtomicFile());

        self::assertNull((new DashboardBuzz($store, false))->recent());
        self::assertSame([], (new DashboardBuzz($store, true))->recent());

        $store->replaceReplies('first-post', [[
            'id' => 'reply-1',
            'text' => 'A thoughtful reply.',
            'username' => 'reader',
            'timestamp' => '2026-07-28T08:00:00+0000',
            'permalink' => 'https://threads.net/t/reply-1',
            'avatar_url' => null,
        ]]);

        $replies = (new DashboardBuzz($store, true))->recent();
        self::assertSame('first-post', $replies[0]['post_slug']);
        self::assertSame('reader', $replies[0]['username']);

        unlink($root . '/threads.json');
        rmdir($root);
    }
}

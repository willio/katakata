<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use Katakata\Distribution\ThreadsApi;
use Katakata\Distribution\ThreadsReplySync;
use Katakata\Distribution\ThreadsStore;
use Katakata\Editorial\AtomicFile;
use PHPUnit\Framework\TestCase;

final class ThreadsReplySyncTest extends TestCase
{
    public function testItIsolatesPerPostReadFailuresAndCachesReplies(): void
    {
        $root = sys_get_temp_dir() . '/katakata-buzz-' . bin2hex(random_bytes(5));
        mkdir($root, 0775, true);
        $store = new ThreadsStore($root . '/state.json', new AtomicFile());
        $store->remember('first', ['id' => 'ok', 'permalink' => null]);
        $store->remember('second', ['id' => 'fail', 'permalink' => null]);
        $api = new class implements ThreadsApi {
            public function publish(string $text): array { return ['id' => 'unused', 'permalink' => null]; }
            public function replies(string $mediaId): array {
                if ($mediaId === 'fail') { throw new \RuntimeException('offline'); }
                return [[
                    'id' => 'reply-1', 'text' => 'Thoughtful.', 'username' => 'reader',
                    'timestamp' => '2026-07-28T08:00:00+0000', 'permalink' => 'https://threads.net/t/reply-1',
                    'avatar_url' => null,
                ]];
            }
        };

        $result = (new ThreadsReplySync($api, $store))->sync();

        self::assertSame(['posts' => 1, 'replies' => 1, 'failed' => 1], $result);
        self::assertSame('first', $store->recentReplies()[0]['post_slug']);
        unlink($root . '/state.json');
        rmdir($root);
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use Katakata\Distribution\ThreadsEngagementSync;
use Katakata\Distribution\ThreadsInsightsApi;
use Katakata\Distribution\ThreadsStore;
use Katakata\Editorial\AtomicFile;
use PHPUnit\Framework\TestCase;

final class ThreadsEngagementSyncTest extends TestCase
{
    public function testItIsolatesPerPostFailuresAndCachesEngagement(): void
    {
        $root = sys_get_temp_dir() . '/katakata-engagement-' . bin2hex(random_bytes(5));
        mkdir($root, 0775, true);
        $store = new ThreadsStore($root . '/state.json', new AtomicFile());
        $store->remember('first', ['id' => 'ok', 'permalink' => null]);
        $store->remember('second', ['id' => 'fail', 'permalink' => null]);
        $api = new class implements ThreadsInsightsApi {
            public function insights(string $mediaId): array
            {
                if ($mediaId === 'fail') {
                    throw new \RuntimeException('offline');
                }
                return ['views' => 12, 'likes' => 3, 'replies' => 2, 'reposts' => 1, 'quotes' => 1, 'shares' => 0];
            }
        };

        $result = (new ThreadsEngagementSync($api, $store))->sync();

        self::assertSame(['posts' => 1, 'failed' => 1], $result);
        self::assertSame([
            'views' => 12,
            'likes' => 3,
            'replies' => 2,
            'reposts' => 1,
            'quotes' => 1,
            'shares' => 0,
        ], $store->engagement()['first']['metrics']);
        self::assertArrayNotHasKey('second', $store->engagement());
        unlink($root . '/state.json');
        rmdir($root);
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use DateTimeImmutable;
use Katakata\Content\Post;
use Katakata\Distribution\ThreadsAdapter;
use Katakata\Distribution\ThreadsApi;
use Katakata\Distribution\ThreadsStore;
use Katakata\Editorial\AtomicFile;
use PHPUnit\Framework\TestCase;

final class ThreadsAdapterTest extends TestCase
{
    public function testItPublishesCanonicalPostAndStoresMapping(): void
    {
        $root = sys_get_temp_dir() . '/katakata-threads-' . bin2hex(random_bytes(5));
        mkdir($root, 0775, true);
        $api = new class implements ThreadsApi {
            public string $text = '';
            public function publish(string $text): array { $this->text = $text; return ['id' => 'media-1', 'permalink' => 'https://threads.net/t/media-1']; }
            public function replies(string $mediaId): array { return []; }
        };
        $store = new ThreadsStore($root . '/state.json', new AtomicFile());
        $adapter = new ThreadsAdapter($api, $store, 'https://example.com');

        $result = $adapter->distribute(new Post(
            'essay', 'An essay', new DateTimeImmutable('2026-07-28'), null, [], 'A concise excerpt.',
            'published', 'Body.', [], '/tmp/essay.md',
        ));

        self::assertSame('media-1', $result['id']);
        self::assertStringContainsString('https://example.com/2026/07/essay', $api->text);
        self::assertSame('media-1', $store->publications()['essay']['media_id']);
        unlink($root . '/state.json');
        rmdir($root);
    }
}

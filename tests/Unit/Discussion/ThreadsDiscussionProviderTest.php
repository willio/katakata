<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Discussion;

use Katakata\Discussion\DiscussionReference;
use Katakata\Discussion\Providers\ThreadsDiscussionProvider;
use Katakata\Distribution\ThreadsApi;
use Katakata\Distribution\ThreadsStore;
use Katakata\Editorial\AtomicFile;
use PHPUnit\Framework\TestCase;

final class ThreadsDiscussionProviderTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/katakata-threads-discussion-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    public function testItCreatesReferencesAndNormalizesFetchedReplies(): void
    {
        $api = new class implements ThreadsApi {
            public function publish(string $text): array
            {
                TestCase::assertSame('Discuss this article', $text);

                return ['id' => 'media-123', 'permalink' => 'https://threads.net/t/media-123'];
            }

            public function replies(string $mediaId): array
            {
                TestCase::assertSame('media-123', $mediaId);

                return [[
                    'id' => 'reply-1',
                    'text' => 'A useful reply',
                    'username' => 'reader',
                    'timestamp' => '2026-07-30T12:00:00+00:00',
                    'permalink' => 'https://threads.net/t/reply-1',
                    'avatar_url' => null,
                ]];
            }
        };
        $store = new ThreadsStore($this->directory . '/threads.json', new AtomicFile());
        $provider = new ThreadsDiscussionProvider($api, $store);

        $reference = $provider->create([
            'slug' => 'article-slug',
            'discussion_text' => 'Discuss this article',
        ]);
        $thread = $provider->fetch($reference);

        self::assertSame('threads', $reference->provider);
        self::assertSame('article-slug', $reference->metadata['post_slug']);
        self::assertSame('reader', $thread->entries[0]->authorName);
        self::assertSame('article-slug', $thread->entries[0]->metadata['post_slug']);
        self::assertCount(1, $provider->recent());
    }
}

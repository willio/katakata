<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Discussion;

use DateTimeImmutable;
use Katakata\Content\Post;
use Katakata\Discussion\DiscussionManager;
use Katakata\Discussion\NativeDiscussionProvider;
use Katakata\Discussion\NativeDiscussionStore;
use Katakata\Discussion\Providers\NullDiscussionProvider;
use Katakata\Discussion\PublicDiscussion;
use Katakata\Editorial\AtomicFile;
use PHPUnit\Framework\TestCase;

final class PublicDiscussionTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/katakata-public-discussion-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    public function testItHonorsPostEnablementAndTheConfiguredProvider(): void
    {
        $native = new NativeDiscussionProvider(new NativeDiscussionStore($this->directory, new AtomicFile()));
        $manager = new DiscussionManager(new NullDiscussionProvider(), $native);

        self::assertNull((new PublicDiscussion($manager, 'native'))->forPost($this->post(false)));

        $discussion = (new PublicDiscussion($manager, 'native'))->forPost($this->post(true));
        self::assertNotNull($discussion);
        self::assertSame('native', $discussion['reference']->provider);

        self::assertNotNull((new PublicDiscussion($manager, 'native', true))->forPost($this->post(null)));
        self::assertNull((new PublicDiscussion($manager, 'none', true))->forPost($this->post(null)));
    }

    private function post(?bool $enabled): Post
    {
        return new Post(
            'article-slug',
            'Article',
            new DateTimeImmutable('2026-08-20'),
            null,
            [],
            null,
            'published',
            'Body',
            $enabled === null ? [] : ['discussion_enabled' => $enabled],
            '/tmp/article.md',
        );
    }
}

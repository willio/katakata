<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Discussion;

use DateTimeImmutable;
use Katakata\Content\Post;
use Katakata\Discussion\NativeDiscussionProvider;
use Katakata\Discussion\NativeDiscussionService;
use Katakata\Discussion\NativeDiscussionStore;
use Katakata\Editorial\AtomicFile;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NativeDiscussionServiceTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/katakata-native-discussion-service-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->path . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->path);
    }

    public function testItStoresPublicSubmissionsAsPending(): void
    {
        $service = $this->service();

        $entry = $service->submit($this->post(), 'Reader', 'A useful comment.');

        self::assertSame('pending', $entry->metadata['moderation_status']);
        self::assertSame([], $service->forPost($this->post())['thread']->entries);
    }

    public function testItOnlyAcceptsRepliesToApprovedEntries(): void
    {
        $store = $this->store();
        $service = new NativeDiscussionService(new NativeDiscussionProvider($store), $store);
        $post = $this->post();
        $reference = $service->forPost($post)['reference'];
        $parent = $store->submit($reference, 'First reader', 'First comment.');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Reply target was not found.');

        $service->submit($post, 'Second reader', 'Reply.', $parent->id);
    }

    private function service(): NativeDiscussionService
    {
        $store = $this->store();

        return new NativeDiscussionService(new NativeDiscussionProvider($store), $store);
    }

    private function store(): NativeDiscussionStore
    {
        return new NativeDiscussionStore($this->path, new AtomicFile());
    }

    private function post(): Post
    {
        return new Post(
            slug: 'native-comments',
            title: 'Native Comments',
            date: new DateTimeImmutable('2026-07-31T00:00:00+00:00'),
            author: null,
            tags: [],
            excerpt: null,
            status: 'published',
            body: 'Article body.',
            meta: [],
            path: '/tmp/native-comments.md',
        );
    }
}

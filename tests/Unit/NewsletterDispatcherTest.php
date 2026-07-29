<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use DateTimeImmutable;
use Katakata\Content\Post;
use Katakata\Distribution\EmailMessage;
use Katakata\Distribution\EmailTransport;
use Katakata\Distribution\MailQueue;
use Katakata\Distribution\NewsletterAdapter;
use Katakata\Distribution\NewsletterDispatcher;
use Katakata\Distribution\SubscriberStore;
use Katakata\Editorial\AtomicFile;
use Katakata\Rendering\Markdown;
use PHPUnit\Framework\TestCase;

final class NewsletterDispatcherTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-dispatch-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $files = glob($this->root . '/{queue,payload/2026/07}/*.json', GLOB_BRACE) ?: [];
        foreach ($files as $file) {
            unlink($file);
        }
        foreach ([$this->root . '/payload/2026/07', $this->root . '/payload/2026', $this->root . '/payload', $this->root . '/queue'] as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
        if (is_file($this->root . '/subscribers.json')) {
            unlink($this->root . '/subscribers.json');
        }
        if (is_dir($this->root)) {
            rmdir($this->root);
        }
    }

    public function testOnlyConfirmedSubscribersAreQueuedOnce(): void
    {
        $files = new AtomicFile();
        $subscribers = new SubscriberStore($this->root . '/subscribers.json', 'secret', $files);
        $active = $subscribers->request('active@example.com');
        $subscribers->confirm($active['confirmation_token']);
        $subscribers->request('pending@example.com');

        $transport = new class implements EmailTransport {
            public function send(EmailMessage $message, string $idempotencyKey): array { return []; }
        };
        $queue = new MailQueue($this->root . '/queue', $transport, $files);
        $dispatcher = new NewsletterDispatcher(
            new NewsletterAdapter($this->root . '/payload', 'https://example.com', new Markdown(), $files),
            $subscribers,
            $queue,
            'https://example.com',
        );
        $post = new Post(
            'essay',
            'An essay',
            new DateTimeImmutable('2026-07-28'),
            null,
            [],
            null,
            'published',
            'Body.',
            [],
            '/tmp/essay.md',
        );

        self::assertSame(1, $dispatcher->dispatch($post)['queued']);
        self::assertSame(1, $dispatcher->dispatch($post)['queued']);
        self::assertCount(1, glob($this->root . '/queue/*.json') ?: []);
        $queued = json_decode((string) file_get_contents((glob($this->root . '/queue/*.json') ?: [])[0]), true);
        self::assertSame('active@example.com', $queued['message']['to']);
        self::assertStringContainsString('/newsletter/unsubscribe?token=', $queued['message']['text']);
    }
}

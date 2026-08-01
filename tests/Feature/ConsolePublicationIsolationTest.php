<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use DateTimeImmutable;
use FilesystemIterator;
use Katakata\Application as Kernel;
use Katakata\Console\Application as ConsoleApplication;
use Katakata\Content\Repository;
use Katakata\Distribution\FilesystemEmailTransport;
use Katakata\Distribution\MailQueue;
use Katakata\Distribution\NewsletterAdapter;
use Katakata\Distribution\NewsletterDispatcher;
use Katakata\Distribution\SubscriberStore;
use Katakata\Editorial\AtomicFile;
use Katakata\Editorial\Publisher;
use Katakata\Editorial\RevisionStore;
use Katakata\Editorial\Scheduler;
use Katakata\Rendering\Markdown;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class ConsolePublicationIsolationTest extends TestCase
{
    private string $root;
    private SubscriberStore $subscribers;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-console-publication-' . bin2hex(random_bytes(6));
        foreach (['content/posts', 'content/drafts', 'content/authors', 'content/assets'] as $path) {
            mkdir($this->root . '/' . $path, 0775, true);
        }
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->root)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    }

    public function testDraftPublishCreatesOnlyTheCanonicalPost(): void
    {
        $console = $this->console();
        $this->subscribe('reader@example.com');
        $this->draft('essay', 'Published directly');

        self::assertSame(0, $console->run(['draft:publish', 'essay', '2026-08-01T10:00:00+07:00']));

        self::assertFileExists($this->root . '/content/posts/2026/08/260801_essay.md');
        self::assertSame([], glob($this->root . '/storage/distribution/mail/queue/*.json') ?: []);
        self::assertSame([], glob($this->root . '/storage/distribution/newsletter/2026/08/*.json') ?: []);
    }

    public function testPublishDueCreatesOnlyTheCanonicalPost(): void
    {
        $console = $this->console();
        $this->subscribe('reader@example.com');
        $this->draft('scheduled-essay', 'Published on schedule', "status: scheduled\npublish_at: 2020-01-01T00:00:00+00:00");

        self::assertSame(0, $console->run(['publish:due']));

        self::assertFileExists($this->root . '/content/posts/2020/01/200101_scheduled-essay.md');
        self::assertSame([], glob($this->root . '/storage/distribution/mail/queue/*.json') ?: []);
        self::assertSame([], glob($this->root . '/storage/distribution/newsletter/2020/01/*.json') ?: []);
    }

    public function testNewsletterDispatchCreatesDeliveryWorkOnlyWhenExplicitlyRequested(): void
    {
        $console = $this->console();
        $this->subscribe('reader@example.com');
        $this->draft('newsletter-essay', 'Newsletter dispatch');

        self::assertSame(0, $console->run(['draft:publish', 'newsletter-essay', '2026-08-01T10:00:00+07:00']));
        self::assertSame(0, $console->run(['newsletter:dispatch', 'newsletter-essay']));

        self::assertCount(1, glob($this->root . '/storage/distribution/mail/queue/*.json') ?: []);
        self::assertFileExists($this->root . '/storage/distribution/newsletter/2026/08/newsletter-essay.json');
    }

    private function console(): ConsoleApplication
    {
        $app = new Kernel($this->root);
        $app->config()->set('app', ['url' => 'https://example.test']);
        $app->config()->set('content', [
            'posts_path' => 'content/posts',
            'drafts_path' => 'content/drafts',
            'authors_path' => 'content/authors',
            'assets_path' => 'content/assets',
        ]);
        $app->config()->freeze();

        $files = new AtomicFile();
        $repository = Repository::forApplication($app);
        $this->subscribers = new SubscriberStore(
            $this->root . '/storage/distribution/subscribers.json',
            'test-newsletter-secret',
            $files,
        );
        $queue = new MailQueue(
            $this->root . '/storage/distribution/mail/queue',
            new FilesystemEmailTransport($this->root . '/storage/distribution/mail/sent', $files),
            $files,
        );

        $app->instance(Repository::class, $repository);
        $app->instance(Publisher::class, new Publisher(
            $this->root . '/content/posts',
            $files,
            new RevisionStore($this->root . '/content/revisions', $files),
        ));
        $app->instance(Scheduler::class, new Scheduler());
        $app->instance(NewsletterDispatcher::class, new NewsletterDispatcher(
            new NewsletterAdapter(
                $this->root . '/storage/distribution/newsletter',
                'https://example.test',
                new Markdown(),
                $files,
            ),
            $this->subscribers,
            $queue,
            'https://example.test',
        ));

        return new ConsoleApplication($app);
    }

    private function subscribe(string $email): void
    {
        $request = $this->subscribers->request($email, new DateTimeImmutable('2026-08-01T09:00:00+07:00'));
        $this->subscribers->confirm($request['confirmation_token'], new DateTimeImmutable('2026-08-01T09:01:00+07:00'));
    }

    private function draft(string $slug, string $title, string $additionalMeta = ''): void
    {
        $meta = $additionalMeta === '' ? '' : $additionalMeta . "\n";
        file_put_contents($this->root . '/content/drafts/' . $slug . '.md', "---\ntitle: {$title}\n{$meta}---\n\nBody.\n");
    }
}

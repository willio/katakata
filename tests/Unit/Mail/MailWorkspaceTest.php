<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Mail;

use Katakata\Content\Repository;
use Katakata\Distribution\SubscriberStore;
use Katakata\Editorial\AtomicFile;
use Katakata\Mail\MailWorkspace;
use Katakata\Rendering\Markdown;
use PHPUnit\Framework\TestCase;

final class MailWorkspaceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-mail-workspace-' . bin2hex(random_bytes(6));

        foreach (['posts/2026/07', 'drafts', 'authors', 'assets', 'storage'] as $path) {
            mkdir($this->root . '/' . $path, 0775, true);
        }
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testItReturnsOnlyPublishedPostsWithNewsletterIntentNewestFirst(): void
    {
        $this->post('260728_ordinary-post.md', ['title' => 'Ordinary post', 'date' => '2026-07-28', 'status' => 'published', 'publish_as_newsletter' => false]);
        $this->post('260729_older-newsletter.md', ['title' => 'Older newsletter', 'date' => '2026-07-29', 'status' => 'published', 'publish_as_newsletter' => true, 'author' => 'will', 'excerpt' => 'Older excerpt.']);
        $this->post('260730_newer-newsletter.md', ['title' => 'Newer newsletter', 'date' => '2026-07-30', 'status' => 'published', 'publish_as_newsletter' => 'true']);
        $this->post('260731_unpublished-newsletter.md', ['title' => 'Unpublished newsletter', 'date' => '2026-07-31', 'status' => 'draft', 'publish_as_newsletter' => true]);

        $queue = $this->workspace()->reviewQueue();

        self::assertSame(['newer-newsletter', 'older-newsletter'], array_column($queue, 'slug'));
        self::assertSame('2026-07-30T00:00:00+00:00', $queue[0]['published_at']);
        self::assertSame('/2026/07/newer-newsletter', $queue[0]['url']);
        self::assertSame('will', $queue[1]['author']);
        self::assertSame('Older excerpt.', $queue[1]['excerpt']);
    }

    public function testRecipientPreviewReturnsOnlyActiveSubscribersWithoutTokens(): void
    {
        $this->subscribers([
            'active@example.com' => ['status' => 'active', 'requested_at' => '2026-07-01T00:00:00+00:00', 'confirmed_at' => '2026-07-02T00:00:00+00:00'],
            'pending@example.com' => ['status' => 'pending', 'requested_at' => '2026-07-03T00:00:00+00:00', 'confirmed_at' => null],
            'unsubscribed@example.com' => ['status' => 'unsubscribed', 'requested_at' => '2026-07-04T00:00:00+00:00', 'confirmed_at' => '2026-07-05T00:00:00+00:00'],
            'suppressed@example.com' => ['status' => 'suppressed', 'requested_at' => '2026-07-06T00:00:00+00:00', 'confirmed_at' => '2026-07-07T00:00:00+00:00'],
        ]);

        $preview = $this->workspace()->recipientPreview();

        self::assertSame(1, $preview['count']);
        self::assertSame([['email' => 'active@example.com', 'confirmed_at' => '2026-07-02T00:00:00+00:00']], $preview['recipients']);
        self::assertArrayNotHasKey('unsubscribe_token', $preview['recipients'][0]);
    }

    public function testRecipientPreviewHandlesAnEmptyAudience(): void
    {
        self::assertSame(['count' => 0, 'recipients' => []], $this->workspace()->recipientPreview());
    }

    public function testCampaignPreviewReturnsOnlyAnEligibleSelectedPost(): void
    {
        $this->post('260730_newsletter.md', ['title' => 'Newsletter', 'date' => '2026-07-30', 'publish_as_newsletter' => true, 'excerpt' => 'Campaign excerpt.']);
        $this->post('260729_ordinary.md', ['title' => 'Ordinary', 'date' => '2026-07-29', 'publish_as_newsletter' => false]);
        $this->subscribers(['reader@example.com' => ['status' => 'active', 'requested_at' => '2026-07-01T00:00:00+00:00', 'confirmed_at' => '2026-07-02T00:00:00+00:00']]);

        $preview = $this->workspace()->campaignPreview('newsletter');

        self::assertNotNull($preview);
        self::assertSame('Newsletter', $preview['post']['title']);
        self::assertSame('Campaign excerpt.', $preview['post']['excerpt']);
        self::assertSame(1, $preview['recipient_count']);
        self::assertNull($this->workspace()->campaignPreview('ordinary'));
        self::assertNull($this->workspace()->campaignPreview('missing'));
    }

    public function testDispatchProofRendersFrozenReadOnlyPayload(): void
    {
        $this->post('260730_newsletter.md', ['title' => 'Newsletter', 'date' => '2026-07-30', 'publish_as_newsletter' => true], "## Hello\n\nA **useful** update.");
        $this->subscribers(['reader@example.com' => ['status' => 'active', 'requested_at' => '2026-07-01T00:00:00+00:00', 'confirmed_at' => '2026-07-02T00:00:00+00:00']]);

        $before = $this->files();
        $proof = $this->workspace()->dispatchProof('newsletter');
        $after = $this->files();

        self::assertNotNull($proof);
        self::assertSame('Newsletter', $proof['subject']);
        self::assertSame('https://example.test/2026/07/newsletter', $proof['canonical_url']);
        self::assertSame(1, $proof['recipient_count']);
        self::assertSame('reader@example.com', $proof['recipients'][0]['email']);
        self::assertStringContainsString('<h2>Hello</h2>', $proof['html']);
        self::assertStringContainsString('<strong>useful</strong>', $proof['html']);
        self::assertStringContainsString('Read on the web', $proof['html']);
        self::assertStringContainsString('Hello', $proof['text']);
        self::assertGreaterThan(0, $proof['estimated_bytes']);
        self::assertSame($before, $after);
        self::assertNull($this->workspace()->dispatchProof('missing'));
    }

    public function testReadingTheWorkspaceDoesNotCreateDistributionState(): void
    {
        $this->post('260730_newsletter.md', ['title' => 'Newsletter', 'date' => '2026-07-30', 'publish_as_newsletter' => true]);

        $before = $this->files();
        $workspace = $this->workspace();
        $queue = $workspace->reviewQueue();
        $audience = $workspace->recipientPreview();
        $campaign = $workspace->campaignPreview('newsletter');
        $proof = $workspace->dispatchProof('newsletter');
        $after = $this->files();

        self::assertCount(1, $queue);
        self::assertSame(0, $audience['count']);
        self::assertNotNull($campaign);
        self::assertNotNull($proof);
        self::assertSame($before, $after);
    }

    private function workspace(): MailWorkspace
    {
        return new MailWorkspace(
            new Repository($this->root . '/posts', $this->root . '/drafts', $this->root . '/authors', $this->root . '/assets'),
            new SubscriberStore($this->root . '/storage/subscribers.json', 'test-newsletter-secret', new AtomicFile()),
            new Markdown(),
            'https://example.test',
        );
    }

    /** @param array<string, array<string, mixed>> $subscribers */
    private function subscribers(array $subscribers): void
    {
        $records = [];
        foreach ($subscribers as $email => $data) {
            $records[hash('sha256', $email)] = ['email' => $email] + $data;
        }

        file_put_contents($this->root . '/storage/subscribers.json', json_encode(['subscribers' => $records, 'confirmations' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    }

    /** @param array<string, scalar|null> $meta */
    private function post(string $filename, array $meta, string $body = 'Article body.'): void
    {
        $frontMatter = ['---'];
        foreach ($meta as $key => $value) {
            $frontMatter[] = $key . ': ' . match (true) {
                $value === true => 'true',
                $value === false => 'false',
                $value === null => 'null',
                default => (string) $value,
            };
        }
        $frontMatter[] = '---';
        $frontMatter[] = '';
        $frontMatter[] = $body;

        file_put_contents($this->root . '/posts/2026/07/' . $filename, implode("\n", $frontMatter) . "\n");
    }

    /** @return list<string> */
    private function files(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = substr($file->getPathname(), strlen($this->root) + 1);
            }
        }

        sort($files);
        return $files;
    }

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}

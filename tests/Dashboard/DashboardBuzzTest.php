<?php

declare(strict_types=1);

namespace Katakata\Tests\Dashboard;

use DateTimeImmutable;
use Katakata\Dashboard\DashboardBuzz;
use Katakata\Discussion\DiscussionEntry;
use Katakata\Discussion\DiscussionManager;
use Katakata\Discussion\DiscussionProvider;
use Katakata\Discussion\DiscussionReference;
use Katakata\Discussion\DiscussionThread;
use Katakata\Discussion\Providers\NullDiscussionProvider;
use PHPUnit\Framework\TestCase;

final class DashboardBuzzTest extends TestCase
{
    public function test_it_distinguishes_disabled_empty_and_cached_reply_states(): void
    {
        $provider = new class implements DiscussionProvider {
            /** @var list<DiscussionEntry> */
            public array $entries = [];

            public function key(): string { return 'threads'; }
            public function isAvailable(): bool { return true; }
            public function supportsReplies(): bool { return true; }
            public function create(array $post): DiscussionReference { return new DiscussionReference('threads', 'root'); }
            public function fetch(DiscussionReference $reference): DiscussionThread { return new DiscussionThread($reference, $this->entries); }
            public function recent(int $limit = 8): array { return array_slice($this->entries, 0, max(0, $limit)); }
            public function synchronize(): array { return ['threads' => 0, 'entries' => 0, 'failed' => 0]; }
        };
        $manager = new DiscussionManager(new NullDiscussionProvider(), $provider);

        self::assertNull((new DashboardBuzz($manager, 'none'))->recent());
        self::assertSame([], (new DashboardBuzz($manager, 'threads'))->recent());

        $provider->entries = [new DiscussionEntry(
            id: 'reply-1',
            authorName: 'reader',
            body: 'A thoughtful reply.',
            publishedAt: new DateTimeImmutable('2026-07-28T08:00:00+00:00'),
            authorUrl: 'https://threads.net/t/reply-1',
            metadata: ['post_slug' => 'first-post', 'permalink' => 'https://threads.net/t/reply-1'],
        )];

        $replies = (new DashboardBuzz($manager, 'threads'))->recent();
        self::assertSame('first-post', $replies[0]['post_slug']);
        self::assertSame('reader', $replies[0]['username']);
    }
}

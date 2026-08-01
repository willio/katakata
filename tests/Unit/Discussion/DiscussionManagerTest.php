<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Discussion;

use Katakata\Discussion\DiscussionManager;
use Katakata\Discussion\DiscussionProvider;
use Katakata\Discussion\DiscussionReference;
use Katakata\Discussion\DiscussionThread;
use Katakata\Discussion\Providers\NullDiscussionProvider;
use PHPUnit\Framework\TestCase;

final class DiscussionManagerTest extends TestCase
{
    public function testItResolvesRegisteredAvailableProvidersAndFallsBackSafely(): void
    {
        $provider = new class implements DiscussionProvider {
            public function key(): string { return 'example'; }
            public function isAvailable(): bool { return true; }
            public function supportsReplies(): bool { return true; }
            public function create(array $post): DiscussionReference { return new DiscussionReference('example', 'id'); }
            public function fetch(DiscussionReference $reference): DiscussionThread { return new DiscussionThread($reference); }
            public function recent(int $limit = 8): array { return []; }
            public function synchronize(): array { return ['threads' => 0, 'entries' => 0, 'failed' => 0]; }
        };
        $fallback = new NullDiscussionProvider();
        $manager = new DiscussionManager($fallback, $provider);

        self::assertSame($provider, $manager->resolve('example'));
        self::assertSame($fallback, $manager->resolve('missing'));
    }
}

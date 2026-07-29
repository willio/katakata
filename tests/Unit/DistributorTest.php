<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use DateTimeImmutable;
use Katakata\Content\Post;
use Katakata\Distribution\Adapter;
use Katakata\Distribution\Distributor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DistributorTest extends TestCase
{
    public function testAdapterFailureDoesNotStopLaterChannels(): void
    {
        $failing = new class implements Adapter {
            public function channel(): string
            {
                return 'threads';
            }

            public function distribute(Post $post): array
            {
                throw new RuntimeException('Threads unavailable.');
            }
        };
        $working = new class implements Adapter {
            public function channel(): string
            {
                return 'newsletter';
            }

            public function distribute(Post $post): array
            {
                return ['id' => $post->slug];
            }
        };

        $deliveries = (new Distributor([$failing, $working]))->distribute($this->post());

        self::assertSame('failed', $deliveries[0]->status);
        self::assertSame('delivered', $deliveries[1]->status);
        self::assertSame('essay', $deliveries[1]->metadata['id']);
    }

    public function testChannelFilterRunsOnlyRequestedAdapter(): void
    {
        $adapter = new class implements Adapter {
            public function channel(): string
            {
                return 'newsletter';
            }

            public function distribute(Post $post): array
            {
                return ['ok' => true];
            }
        };

        self::assertCount(1, (new Distributor([$adapter]))->distribute($this->post(), 'newsletter'));
        self::assertSame([], (new Distributor([$adapter]))->distribute($this->post(), 'threads'));
    }

    private function post(): Post
    {
        return new Post(
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
    }
}

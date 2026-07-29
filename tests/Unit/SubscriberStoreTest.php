<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use DateTimeImmutable;
use Katakata\Distribution\SubscriberStore;
use Katakata\Editorial\AtomicFile;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SubscriberStoreTest extends TestCase
{
    private string $root;
    private string $path;
    private SubscriberStore $store;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-subscribers-' . bin2hex(random_bytes(6));
        $this->path = $this->root . '/subscribers.json';
        $this->store = new SubscriberStore($this->path, 'test-secret', new AtomicFile());
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
        if (is_dir($this->root)) {
            rmdir($this->root);
        }
    }

    public function testSubscriptionRequiresConfirmationBeforeDelivery(): void
    {
        $request = $this->store->request('Writer@Example.com', new DateTimeImmutable('2026-07-28T10:00:00Z'));

        self::assertSame([], $this->store->active());

        $subscriber = $this->store->confirm(
            $request['confirmation_token'],
            new DateTimeImmutable('2026-07-28T11:00:00Z'),
        );

        self::assertSame('writer@example.com', $subscriber['email']);
        self::assertSame('active', $subscriber['status']);
        self::assertCount(1, $this->store->active());
    }

    public function testConfirmationTokenIsSingleUse(): void
    {
        $request = $this->store->request('reader@example.com');
        $this->store->confirm($request['confirmation_token']);

        $this->expectException(RuntimeException::class);
        $this->store->confirm($request['confirmation_token']);
    }

    public function testExpiredConfirmationIsRejected(): void
    {
        $request = $this->store->request(
            'reader@example.com',
            new DateTimeImmutable('2026-07-20T10:00:00Z'),
        );

        $this->expectException(RuntimeException::class);
        $this->store->confirm(
            $request['confirmation_token'],
            new DateTimeImmutable('2026-07-23T10:00:01Z'),
        );
    }

    public function testUnsubscribeImmediatelyRemovesDeliveryEligibility(): void
    {
        $request = $this->store->request('reader@example.com');
        $subscriber = $this->store->confirm($request['confirmation_token']);

        $result = $this->store->unsubscribe($subscriber['unsubscribe_token']);

        self::assertSame('unsubscribed', $result['status']);
        self::assertSame([], $this->store->active());
    }

    public function testInvalidEmailIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->store->request('not-an-email');
    }
}

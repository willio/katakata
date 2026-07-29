<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use DateTimeImmutable;
use Katakata\Distribution\EmailMessage;
use Katakata\Distribution\EmailTransport;
use Katakata\Distribution\MailQueue;
use Katakata\Editorial\AtomicFile;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MailQueueTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-mail-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/*.json') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->root)) {
            rmdir($this->root);
        }
    }

    public function testEnqueueIsIdempotentAndDeliveryIsRecorded(): void
    {
        $counter = (object) ['sent' => 0];
        $transport = new class($counter) implements EmailTransport {
            public function __construct(private object $counter) {}
            public function send(EmailMessage $message, string $idempotencyKey): array
            {
                $this->counter->sent++;
                return ['id' => $idempotencyKey];
            }
        };
        $queue = new MailQueue($this->root, $transport, new AtomicFile());
        $message = new EmailMessage('reader@example.com', 'Subject', '<p>Body</p>', 'Body');

        self::assertSame($queue->enqueue('same-key', $message), $queue->enqueue('same-key', $message));
        self::assertSame(['processed' => 1, 'delivered' => 1, 'failed' => 0], $queue->work());
        self::assertSame(['processed' => 0, 'delivered' => 0, 'failed' => 0], $queue->work());
        self::assertSame(1, $counter->sent);
    }

    public function testFailureIsRetriedAfterBackoff(): void
    {
        $transport = new class implements EmailTransport {
            public int $attempts = 0;
            public function send(EmailMessage $message, string $idempotencyKey): array
            {
                if (++$this->attempts === 1) {
                    throw new RuntimeException('Temporary outage.');
                }
                return [];
            }
        };
        $queue = new MailQueue($this->root, $transport, new AtomicFile());
        $queue->enqueue(
            'retry-key',
            new EmailMessage('reader@example.com', 'Subject', '', 'Body'),
            new DateTimeImmutable('2026-07-28T10:00:00Z'),
        );

        self::assertSame(1, $queue->work(50, new DateTimeImmutable('2026-07-28T10:00:00Z'))['failed']);
        self::assertSame(1, $queue->work(50, new DateTimeImmutable('2026-07-28T10:01:00Z'))['delivered']);
    }
}

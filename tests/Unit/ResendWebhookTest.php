<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use DateTimeImmutable;
use InvalidArgumentException;
use Katakata\Distribution\ResendWebhook;
use Katakata\Distribution\SubscriberStore;
use Katakata\Editorial\AtomicFile;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ResendWebhookTest extends TestCase
{
    private string $root;
    private string $secret;
    private SubscriberStore $subscribers;
    private ResendWebhook $webhook;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-resend-' . bin2hex(random_bytes(6));
        $this->secret = 'whsec_' . base64_encode('webhook-test-secret');
        $files = new AtomicFile();
        $this->subscribers = new SubscriberStore(
            $this->root . '/subscribers.json',
            'newsletter-test-secret',
            $files,
        );
        $this->webhook = new ResendWebhook(
            $this->root . '/webhooks',
            $this->secret,
            $this->subscribers,
            $files,
        );
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/webhooks/*.json') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->root . '/webhooks')) {
            rmdir($this->root . '/webhooks');
        }
        if (is_file($this->root . '/subscribers.json')) {
            unlink($this->root . '/subscribers.json');
        }
        if (is_dir($this->root)) {
            rmdir($this->root);
        }
    }

    public function testBounceIsRecordedOnceAndSuppressesTheSubscriber(): void
    {
        $this->activate('reader@example.com');
        [$payload, $headers] = $this->event('email.bounced', 'reader@example.com');

        $first = $this->webhook->handle(
            $payload,
            $headers,
            new DateTimeImmutable('@' . $headers['svix-timestamp']),
        );
        $second = $this->webhook->handle(
            $payload,
            $headers,
            new DateTimeImmutable('@' . $headers['svix-timestamp']),
        );

        self::assertFalse($first['duplicate']);
        self::assertSame(1, $first['suppressed']);
        self::assertTrue($second['duplicate']);
        self::assertSame([], $this->subscribers->deliverable());
        self::assertCount(1, glob($this->root . '/webhooks/*.json') ?: []);
        self::assertStringNotContainsString(
            'reader@example.com',
            (string) file_get_contents((glob($this->root . '/webhooks/*.json') ?: [])[0]),
        );
    }

    public function testComplaintSuppressionCannotBeBypassedByResubscribing(): void
    {
        $this->activate('reader@example.com');
        [$payload, $headers] = $this->event('email.complained', 'reader@example.com');
        $this->webhook->handle(
            $payload,
            $headers,
            new DateTimeImmutable('@' . $headers['svix-timestamp']),
        );

        $this->expectException(RuntimeException::class);
        $this->subscribers->request('reader@example.com');
    }

    public function testDeliveredEventDoesNotSuppressTheSubscriber(): void
    {
        $this->activate('reader@example.com');
        [$payload, $headers] = $this->event('email.delivered', 'reader@example.com');

        $result = $this->webhook->handle(
            $payload,
            $headers,
            new DateTimeImmutable('@' . $headers['svix-timestamp']),
        );

        self::assertSame(0, $result['suppressed']);
        self::assertCount(1, $this->subscribers->deliverable());
    }

    public function testInvalidSignatureCannotMutateSubscriberState(): void
    {
        $this->activate('reader@example.com');
        [$payload, $headers] = $this->event('email.bounced', 'reader@example.com');
        $headers['svix-signature'] = 'v1,invalid';

        try {
            $this->webhook->handle(
                $payload,
                $headers,
                new DateTimeImmutable('@' . $headers['svix-timestamp']),
            );
            self::fail('Invalid signature was accepted.');
        } catch (InvalidArgumentException) {
            self::assertCount(1, $this->subscribers->deliverable());
            self::assertSame([], glob($this->root . '/webhooks/*.json') ?: []);
        }
    }

    private function activate(string $email): void
    {
        $subscription = $this->subscribers->request($email);
        $this->subscribers->confirm($subscription['confirmation_token']);
    }

    /** @return array{string, array{svix-id: string, svix-timestamp: string, svix-signature: string}} */
    private function event(string $type, string $email): array
    {
        $payload = json_encode([
            'type' => $type,
            'created_at' => '2026-07-30T00:00:00Z',
            'data' => [
                'email_id' => 'email_123',
                'to' => [$email],
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $id = 'msg_' . bin2hex(random_bytes(6));
        $timestamp = (string) time();
        $key = base64_decode(substr($this->secret, strlen('whsec_')), true);
        $signature = base64_encode(hash_hmac(
            'sha256',
            $id . '.' . $timestamp . '.' . $payload,
            $key,
            true,
        ));

        return [$payload, [
            'svix-id' => $id,
            'svix-timestamp' => $timestamp,
            'svix-signature' => 'v1,' . $signature,
        ]];
    }
}

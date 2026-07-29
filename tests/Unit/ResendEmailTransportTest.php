<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use Katakata\Distribution\EmailMessage;
use Katakata\Distribution\ResendEmailTransport;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ResendEmailTransportTest extends TestCase
{
    public function testItSendsTheProviderNeutralMessageWithIdempotency(): void
    {
        $request = [];
        $transport = new ResendEmailTransport(
            're_test',
            'Katakata <letters@example.com>',
            static function (string $url, string $payload, array $headers) use (&$request): array {
                $request = compact('url', 'payload', 'headers');
                return ['status' => 200, 'body' => '{"id":"email_123"}'];
            },
        );

        self::assertSame(
            ['provider' => 'resend', 'id' => 'email_123'],
            $transport->send(new EmailMessage('reader@example.com', 'Essay', '<p>Body</p>', 'Body'), 'mail-key'),
        );
        self::assertSame('https://api.resend.com/emails', $request['url']);
        self::assertContains('Authorization: Bearer re_test', $request['headers']);
        self::assertContains('Idempotency-Key: mail-key', $request['headers']);
        self::assertSame('reader@example.com', json_decode($request['payload'], true)['to'][0]);
    }

    public function testProviderErrorsRemainRetryableQueueExceptions(): void
    {
        $transport = new ResendEmailTransport(
            're_test',
            'letters@example.com',
            static fn (): array => ['status' => 429, 'body' => '{"message":"Rate limited"}'],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 429: Rate limited');
        $transport->send(new EmailMessage('reader@example.com', 'Essay', '', 'Body'), 'mail-key');
    }

    public function testCredentialsAreRequired(): void
    {
        $this->expectException(RuntimeException::class);
        new ResendEmailTransport('', '');
    }
}

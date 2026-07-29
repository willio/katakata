<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use Katakata\Distribution\EmailMessage;
use Katakata\Distribution\EmailTransport;
use Katakata\Distribution\EmailTransportRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EmailTransportRegistryTest extends TestCase
{
    public function testItResolvesNamedTransportsLazilyAndOnlyOnce(): void
    {
        $created = 0;
        $transport = new class implements EmailTransport {
            public function send(EmailMessage $message, string $idempotencyKey): array
            {
                return ['id' => $idempotencyKey];
            }
        };
        $registry = (new EmailTransportRegistry())->register(
            'Example',
            static function () use (&$created, $transport): EmailTransport {
                $created++;
                return $transport;
            },
        );

        self::assertSame(['example'], $registry->names());
        self::assertSame(0, $created);
        self::assertSame($transport, $registry->resolve('example'));
        self::assertSame($transport, $registry->resolve('EXAMPLE'));
        self::assertSame(1, $created);
    }

    public function testItRejectsUnknownTransports(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported mail transport [missing].');

        (new EmailTransportRegistry())->resolve('missing');
    }

    public function testItRejectsDuplicateTransportNames(): void
    {
        $registry = (new EmailTransportRegistry())->register(
            'example',
            static fn (): EmailTransport => new class implements EmailTransport {
                public function send(EmailMessage $message, string $idempotencyKey): array
                {
                    return [];
                }
            },
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mail transport [example] is already registered.');

        $registry->register(
            'EXAMPLE',
            static fn (): EmailTransport => new class implements EmailTransport {
                public function send(EmailMessage $message, string $idempotencyKey): array
                {
                    return [];
                }
            },
        );
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Email;

use Katakata\Email\ImapSettings;
use PHPUnit\Framework\TestCase;

final class ImapSettingsTest extends TestCase
{
    public function testPublicStatusNeverExposesCredentials(): void
    {
        $settings = new ImapSettings(
            host: 'imap.example.test',
            port: 993,
            encryption: 'ssl',
            username: 'reader@example.test',
            password: 'secret-value',
            mailbox: 'INBOX',
        );

        $status = $settings->publicStatus();

        self::assertSame('imap.example.test', $status['host']);
        self::assertSame(993, $status['port']);
        self::assertSame('ssl', $status['encryption']);
        self::assertSame('INBOX', $status['mailbox']);
        self::assertSame(extension_loaded('openssl'), $status['transport_available']);
        self::assertArrayNotHasKey('username', $status);
        self::assertArrayNotHasKey('password', $status);
        self::assertStringNotContainsString('secret-value', json_encode($status, JSON_THROW_ON_ERROR));
    }

    public function testMissingCredentialsProduceExplicitReadinessFields(): void
    {
        $settings = new ImapSettings('', 993, 'ssl', '', '', 'INBOX');

        self::assertFalse($settings->configured());
        self::assertSame(['IMAP_HOST', 'IMAP_USERNAME', 'IMAP_PASSWORD'], $settings->missing());
    }

    public function testPlaintextAndStartTlsAreRejected(): void
    {
        foreach (['none', 'tls', ''] as $encryption) {
            $settings = new ImapSettings(
                host: 'imap.example.test',
                port: 993,
                encryption: $encryption,
                username: 'reader@example.test',
                password: 'secret-value',
                mailbox: 'INBOX',
            );

            self::assertFalse($settings->configured());
            self::assertContains('IMAP_ENCRYPTION', $settings->missing());
        }
    }
}

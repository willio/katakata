<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Email;

use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;
use Katakata\Email\Import\ImportedMailboxAccount;
use Katakata\Email\Import\MailProfileImportStore;
use PHPUnit\Framework\TestCase;

final class MailProfileImportStoreTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/katakata-mail-import-' . bin2hex(random_bytes(5));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*.json') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($this->directory);
    }

    public function testItStoresCredentialFreeCandidatesForFifteenMinutesAndConsumesOnce(): void
    {
        $store = new MailProfileImportStore($this->directory, new AtomicFile());
        $now = new DateTimeImmutable('2026-08-02T10:00:00+00:00');
        $candidate = new ImportedMailboxAccount(
            label: 'Letters',
            emailAddress: 'letters@example.test',
            incomingHost: 'imap.example.test',
            incomingPort: 993,
            incomingEncryption: 'ssl',
            incomingUsername: 'letters@example.test',
            incomingMailbox: 'INBOX',
            embeddedCredentialDetected: true,
            warnings: ['Embedded credential detected; it will not be persisted.'],
        );

        $token = $store->create([$candidate], $now);
        $path = $this->directory . '/' . $token . '.json';
        $saved = (string) file_get_contents($path);

        self::assertStringNotContainsString('actual-password', $saved);
        self::assertSame('Letters', $store->consume($token, 0, $now->modify('+14 minutes'))['label'] ?? null);
        self::assertNull($store->consume($token, 0, $now->modify('+14 minutes')));
    }

    public function testExpiredImportCannotBeConsumed(): void
    {
        $store = new MailProfileImportStore($this->directory, new AtomicFile());
        $now = new DateTimeImmutable('2026-08-02T10:00:00+00:00');
        $token = $store->create([new ImportedMailboxAccount(
            label: 'Letters',
            emailAddress: '',
            incomingHost: 'imap.example.test',
            incomingPort: 993,
            incomingEncryption: 'ssl',
            incomingUsername: '',
            incomingMailbox: 'INBOX',
        )], $now);

        self::assertNull($store->consume($token, 0, $now->modify('+16 minutes')));
    }
}

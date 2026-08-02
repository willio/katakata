<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Email;

use Katakata\Editorial\AtomicFile;
use Katakata\Email\MailboxAccount;
use Katakata\Email\MailboxAccountStore;
use PHPUnit\Framework\TestCase;

final class MailboxAccountStoreTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/katakata-mail-accounts-' . bin2hex(random_bytes(5));
    }

    protected function tearDown(): void
    {
        if (is_file($this->directory . '/accounts.json')) {
            @unlink($this->directory . '/accounts.json');
        }
        @rmdir($this->directory);
    }

    public function testItPersistsOnlyNonSecretAccountConfiguration(): void
    {
        $store = new MailboxAccountStore($this->directory . '/accounts.json', new AtomicFile());
        $store->create($this->account('letters'));

        $saved = (string) file_get_contents($this->directory . '/accounts.json');
        self::assertStringContainsString('IMAP_LETTERS_USERNAME', $saved);
        self::assertStringContainsString('IMAP_LETTERS_PASSWORD', $saved);
        self::assertStringNotContainsString('reader@example.test', $saved);
        self::assertStringNotContainsString('actual-password', $saved);
        self::assertSame('Letters', $store->find('letters')?->label);
    }

    public function testItEnforcesThreeAccountLimitAndUniqueIds(): void
    {
        $store = new MailboxAccountStore($this->directory . '/accounts.json', new AtomicFile());
        foreach (['one', 'two', 'three'] as $id) {
            $store->create($this->account($id));
        }

        $this->expectExceptionMessage('Mailbox account limit reached.');
        $store->create($this->account('four'));
    }

    private function account(string $id): MailboxAccount
    {
        return new MailboxAccount(
            id: $id,
            label: ucfirst($id),
            host: 'imap.example.test',
            port: 993,
            encryption: 'ssl',
            mailbox: 'INBOX',
            usernameSecret: 'IMAP_' . strtoupper($id) . '_USERNAME',
            passwordSecret: 'IMAP_' . strtoupper($id) . '_PASSWORD',
        );
    }
}

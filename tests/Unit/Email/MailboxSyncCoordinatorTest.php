<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Email;

use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;
use Katakata\Email\ImapMailboxSource;
use Katakata\Email\ImapSettings;
use Katakata\Email\MailboxAccount;
use Katakata\Email\MailboxAccountStore;
use Katakata\Email\MailboxCredentialResolver;
use Katakata\Email\MailboxSyncCoordinator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MailboxSyncCoordinatorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-multi-sync-' . bin2hex(random_bytes(5));
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testItSynchronizesEnabledAccountsIntoIsolatedCachesAndContainsFailure(): void
    {
        $store = new MailboxAccountStore($this->root . '/accounts.json', new AtomicFile());
        $store->create($this->account('letters', true));
        $store->create($this->account('admin', true));
        $store->create($this->account('disabled', false));
        $environment = static fn (string $name): string => str_ends_with($name, '_USERNAME') ? 'reader' : 'secret';
        $credentials = new MailboxCredentialResolver($environment);
        $sources = static function (MailboxAccount $account): ImapMailboxSource {
            return new class($account->id) implements ImapMailboxSource {
                public function __construct(private readonly string $accountId)
                {
                }

                public function fetch(ImapSettings $settings, int $limit = 100): array
                {
                    if ($this->accountId === 'admin') {
                        throw new RuntimeException('Mailbox unavailable.');
                    }
                    return [[
                        'id' => 'uid-1',
                        'from' => 'reader@example.test',
                        'to' => 'letters@example.test',
                        'subject' => 'Hello',
                        'text' => 'Message',
                        'html' => '<p>Must not persist</p>',
                        'received_at' => '2026-08-02T10:00:00+00:00',
                        'attachments' => [],
                    ]];
                }
            };
        };
        $coordinator = new MailboxSyncCoordinator(
            $store,
            $credentials,
            $this->root . '/cache',
            new AtomicFile(),
            $sources,
        );

        $results = $coordinator->syncEnabled(10, new DateTimeImmutable('2026-08-02T11:00:00+00:00'));

        self::assertSame('ready', $results['letters']['status']);
        self::assertSame('error', $results['admin']['status']);
        self::assertArrayNotHasKey('disabled', $results);
        self::assertFileExists($this->root . '/cache/letters/messages/uid-1.json');
        self::assertFileDoesNotExist($this->root . '/cache/disabled/index.json');
        self::assertStringNotContainsString('html', (string) file_get_contents($this->root . '/cache/letters/messages/uid-1.json'));
    }

    private function account(string $id, bool $enabled): MailboxAccount
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
            enabled: $enabled,
        );
    }

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            @unlink($path);
            return;
        }
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->remove($path . DIRECTORY_SEPARATOR . $item);
        }
        @rmdir($path);
    }
}

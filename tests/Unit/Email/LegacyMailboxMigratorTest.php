<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Email;

use Katakata\Editorial\AtomicFile;
use Katakata\Email\ImapSettings;
use Katakata\Email\LegacyMailboxMigrator;
use Katakata\Email\MailboxAccountStore;
use PHPUnit\Framework\TestCase;

final class LegacyMailboxMigratorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-legacy-mail-' . bin2hex(random_bytes(5));
        mkdir($this->root . '/cache/messages', 0700, true);
        file_put_contents($this->root . '/cache/index.json', "{\"messages\":[\"uid-1\"]}\n");
        file_put_contents($this->root . '/cache/state.json', "{\"read\":[],\"archived\":[],\"deleted\":{}}\n");
        file_put_contents($this->root . '/cache/messages/uid-1.json', "{\"id\":\"uid-1\"}\n");
    }

    protected function tearDown(): void
    {
        $remove = static function (string $path) use (&$remove): void {
            if (!is_dir($path)) {
                @unlink($path);
                return;
            }
            foreach (scandir($path) ?: [] as $item) {
                if ($item !== '.' && $item !== '..') {
                    $remove($path . '/' . $item);
                }
            }
            @rmdir($path);
        };
        $remove($this->root);
    }

    public function testItCreatesDefaultAccountAndMovesLegacyCacheWithoutMessageLoss(): void
    {
        $store = new MailboxAccountStore($this->root . '/accounts.json', new AtomicFile());
        $migrator = new LegacyMailboxMigrator($store, $this->root . '/cache');

        $migrated = $migrator->migrate(new ImapSettings(
            'imap.example.test', 993, 'ssl', 'reader', 'secret', 'INBOX',
        ));

        self::assertTrue($migrated);
        self::assertSame('default', $store->find('default')?->id);
        self::assertSame('IMAP_USERNAME', $store->find('default')?->usernameSecret);
        self::assertFileExists($this->root . '/cache/default/index.json');
        self::assertFileExists($this->root . '/cache/default/state.json');
        self::assertFileExists($this->root . '/cache/default/messages/uid-1.json');
        self::assertFileDoesNotExist($this->root . '/cache/messages/uid-1.json');
    }

    public function testItDoesNothingWhenRegistryAlreadyExists(): void
    {
        $store = new MailboxAccountStore($this->root . '/accounts.json', new AtomicFile());
        $store->create(new \Katakata\Email\MailboxAccount(
            'letters', 'Letters', 'imap.example.test', 993, 'ssl', 'INBOX',
            'IMAP_LETTERS_USERNAME', 'IMAP_LETTERS_PASSWORD', true,
        ));

        self::assertFalse((new LegacyMailboxMigrator($store, $this->root . '/cache'))->migrate(
            new ImapSettings('imap.example.test', 993, 'ssl', 'reader', 'secret', 'INBOX'),
        ));
        self::assertFileExists($this->root . '/cache/messages/uid-1.json');
    }
}

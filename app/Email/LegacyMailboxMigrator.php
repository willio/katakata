<?php

declare(strict_types=1);

namespace Katakata\Email;

use RuntimeException;

final class LegacyMailboxMigrator
{
    public function __construct(
        private readonly MailboxAccountStore $accounts,
        private readonly string $cacheRoot,
    ) {
    }

    public function migrate(?ImapSettings $legacy = null): bool
    {
        if ($this->accounts->all() !== []) {
            return false;
        }

        $legacy ??= ImapSettings::fromEnvironment();
        if (!$legacy->configured()) {
            return false;
        }

        $this->accounts->create(new MailboxAccount(
            id: 'default',
            label: 'Default mailbox',
            host: $legacy->host,
            port: $legacy->port,
            encryption: 'ssl',
            mailbox: $legacy->mailbox,
            usernameSecret: 'IMAP_USERNAME',
            passwordSecret: 'IMAP_PASSWORD',
            enabled: true,
        ));

        $this->moveLegacyCache();
        return true;
    }

    private function moveLegacyCache(): void
    {
        $targetRoot = $this->cacheRoot . '/default';
        foreach (['index.json', 'state.json', 'messages', 'attachments'] as $name) {
            $source = $this->cacheRoot . '/' . $name;
            if (!file_exists($source)) {
                continue;
            }
            $target = $targetRoot . '/' . $name;
            if (file_exists($target)) {
                throw new RuntimeException('Default mailbox cache already exists; legacy migration was not completed.');
            }
            if (!is_dir($targetRoot) && !mkdir($targetRoot, 0700, true) && !is_dir($targetRoot)) {
                throw new RuntimeException('Unable to create the default mailbox cache directory.');
            }
            if (!rename($source, $target)) {
                throw new RuntimeException('Unable to move the legacy mailbox cache.');
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Email;

use Katakata\Editorial\AtomicFile;
use RuntimeException;

final class MailboxAccountStore
{
    public const MAX_ACCOUNTS = 3;

    public function __construct(
        private readonly string $path,
        private readonly AtomicFile $files,
    ) {
    }

    /** @return list<MailboxAccount> */
    public function all(): array
    {
        if (!is_file($this->path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($this->path), true);
        if (!is_array($data)) {
            throw new RuntimeException('Mailbox account registry is invalid.');
        }
        return array_map(
            static fn (array $account): MailboxAccount => MailboxAccount::fromArray($account),
            array_values(array_filter((array) ($data['accounts'] ?? []), 'is_array')),
        );
    }

    public function find(string $id): ?MailboxAccount
    {
        foreach ($this->all() as $account) {
            if ($account->id === $id) {
                return $account;
            }
        }
        return null;
    }

    public function create(MailboxAccount $account): void
    {
        $accounts = $this->all();
        if (count($accounts) >= self::MAX_ACCOUNTS) {
            throw new RuntimeException('Mailbox account limit reached.');
        }
        if ($this->find($account->id) !== null) {
            throw new RuntimeException('Mailbox account ID already exists.');
        }
        $accounts[] = $account;
        $this->write($accounts);
    }

    public function update(MailboxAccount $account): void
    {
        $accounts = $this->all();
        $found = false;
        foreach ($accounts as $index => $existing) {
            if ($existing->id === $account->id) {
                $accounts[$index] = $account;
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new RuntimeException('Mailbox account does not exist.');
        }
        $this->write($accounts);
    }

    public function delete(string $id): void
    {
        $accounts = array_values(array_filter(
            $this->all(),
            static fn (MailboxAccount $account): bool => $account->id !== $id,
        ));
        $this->write($accounts);
    }

    /** @param list<MailboxAccount> $accounts */
    private function write(array $accounts): void
    {
        $payload = [
            'version' => 1,
            'accounts' => array_map(static fn (MailboxAccount $account): array => $account->toArray(), $accounts),
        ];
        $this->files->write(
            $this->path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        );
        @chmod($this->path, 0600);
    }
}

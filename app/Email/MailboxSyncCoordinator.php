<?php

declare(strict_types=1);

namespace Katakata\Email;

use Closure;
use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;
use RuntimeException;

final class MailboxSyncCoordinator
{
    /** @var Closure(MailboxAccount):ImapMailboxSource */
    private Closure $sources;

    /** @param null|Closure(MailboxAccount):ImapMailboxSource $sources */
    public function __construct(
        private readonly MailboxAccountStore $accounts,
        private readonly MailboxCredentialResolver $credentials,
        private readonly string $cacheRoot,
        private readonly AtomicFile $files,
        ?Closure $sources = null,
    ) {
        $this->sources = $sources ?? static fn (): ImapMailboxSource => new SocketImapMailboxSource(new MailTextExtractor());
    }

    /** @return array<string,array<string,mixed>> */
    public function syncEnabled(int $limit = 100, ?DateTimeImmutable $now = null): array
    {
        $results = [];
        foreach ($this->accounts->all() as $account) {
            if (!$account->enabled) {
                continue;
            }
            try {
                $results[$account->id] = ['status' => 'ready'] + $this->syncAccount($account->id, $limit, $now);
            } catch (\Throwable $error) {
                $results[$account->id] = [
                    'status' => 'error',
                    'label' => $account->label,
                    'error' => $this->safeError($error),
                ];
            }
        }
        return $results;
    }

    /** @return array<string,mixed> */
    public function syncAccount(string $accountId, int $limit = 100, ?DateTimeImmutable $now = null): array
    {
        $account = $this->accounts->find($accountId);
        if ($account === null) {
            throw new RuntimeException('Mailbox account does not exist.');
        }
        if (!$account->enabled) {
            throw new RuntimeException('Mailbox account is disabled.');
        }

        $settings = $this->credentials->settings($account);
        $sync = new ImapSynchronizer(
            $settings,
            ($this->sources)($account),
            $this->cacheRoot . '/' . $account->id,
            $this->files,
        );

        return ['label' => $account->label] + $sync->sync(max(1, $limit), $now);
    }

    private function safeError(\Throwable $error): string
    {
        $message = $error->getMessage();
        foreach ($this->accounts->all() as $account) {
            foreach ([$account->usernameSecret, $account->passwordSecret] as $name) {
                $value = getenv($name);
                if (is_string($value) && $value !== '') {
                    $message = str_replace($value, '[redacted]', $message);
                }
            }
        }
        return $message !== '' ? $message : 'Mailbox synchronization failed.';
    }
}

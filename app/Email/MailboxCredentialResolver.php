<?php

declare(strict_types=1);

namespace Katakata\Email;

final class MailboxCredentialResolver
{
    /** @param null|callable(string):string|false $environment */
    public function __construct(private $environment = null)
    {
        $this->environment ??= static fn (string $name): string|false => getenv($name);
    }

    public function settings(MailboxAccount $account): ImapSettings
    {
        $username = ($this->environment)($account->usernameSecret);
        $password = ($this->environment)($account->passwordSecret);

        return new ImapSettings(
            $account->host,
            $account->port,
            $account->encryption,
            is_string($username) ? $username : '',
            is_string($password) ? $password : '',
            $account->mailbox,
        );
    }

    /** @return list<string> */
    public function missing(MailboxAccount $account): array
    {
        $missing = [];
        foreach ([$account->usernameSecret, $account->passwordSecret] as $name) {
            $value = ($this->environment)($name);
            if (!is_string($value) || $value === '') {
                $missing[] = $name;
            }
        }
        return $missing;
    }
}

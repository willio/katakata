<?php

declare(strict_types=1);

namespace Katakata\Email;

use InvalidArgumentException;

final readonly class MailboxAccount
{
    public function __construct(
        public string $id,
        public string $label,
        public string $host,
        public int $port,
        public string $encryption,
        public string $mailbox,
        public string $usernameSecret,
        public string $passwordSecret,
        public bool $enabled = true,
    ) {
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,31}$/', $id)) {
            throw new InvalidArgumentException('Mailbox account ID is invalid.');
        }
        if (trim($label) === '' || trim($host) === '') {
            throw new InvalidArgumentException('Mailbox label and host are required.');
        }
        if ($port < 1 || $port > 65535 || strtolower($encryption) !== 'ssl') {
            throw new InvalidArgumentException('Mailbox account must use direct TLS on a valid port.');
        }
        foreach ([$usernameSecret, $passwordSecret] as $secret) {
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $secret)) {
                throw new InvalidArgumentException('Mailbox credential secret name is invalid.');
            }
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => 'ssl',
            'mailbox' => $this->mailbox,
            'username_secret' => $this->usernameSecret,
            'password_secret' => $this->passwordSecret,
            'enabled' => $this->enabled,
        ];
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            label: trim((string) ($data['label'] ?? '')),
            host: trim((string) ($data['host'] ?? '')),
            port: (int) ($data['port'] ?? 993),
            encryption: (string) ($data['encryption'] ?? 'ssl'),
            mailbox: trim((string) ($data['mailbox'] ?? 'INBOX')) ?: 'INBOX',
            usernameSecret: (string) ($data['username_secret'] ?? ''),
            passwordSecret: (string) ($data['password_secret'] ?? ''),
            enabled: (bool) ($data['enabled'] ?? true),
        );
    }
}

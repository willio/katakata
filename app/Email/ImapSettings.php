<?php

declare(strict_types=1);

namespace Katakata\Email;

final readonly class ImapSettings
{
    public function __construct(
        public string $host,
        public int $port,
        public string $encryption,
        public string $username,
        public string $password,
        public string $mailbox,
    ) {
    }

    public static function fromEnvironment(): self
    {
        return new self(
            host: trim((string) ($_ENV['IMAP_HOST'] ?? getenv('IMAP_HOST') ?: '')),
            port: max(1, (int) ($_ENV['IMAP_PORT'] ?? getenv('IMAP_PORT') ?: 993)),
            encryption: strtolower(trim((string) ($_ENV['IMAP_ENCRYPTION'] ?? getenv('IMAP_ENCRYPTION') ?: 'ssl'))),
            username: trim((string) ($_ENV['IMAP_USERNAME'] ?? getenv('IMAP_USERNAME') ?: '')),
            password: (string) ($_ENV['IMAP_PASSWORD'] ?? getenv('IMAP_PASSWORD') ?: ''),
            mailbox: trim((string) ($_ENV['IMAP_MAILBOX'] ?? getenv('IMAP_MAILBOX') ?: 'INBOX')),
        );
    }

    public function configured(): bool
    {
        return $this->host !== '' && $this->username !== '' && $this->password !== '' && $this->mailbox !== '';
    }

    /** @return list<string> */
    public function missing(): array
    {
        $missing = [];
        foreach ([
            'IMAP_HOST' => $this->host,
            'IMAP_USERNAME' => $this->username,
            'IMAP_PASSWORD' => $this->password,
            'IMAP_MAILBOX' => $this->mailbox,
        ] as $name => $value) {
            if ($value === '') {
                $missing[] = $name;
            }
        }
        return $missing;
    }

    /** @return array{host:string,port:int,encryption:string,mailbox:string,configured:bool,missing:list<string>} */
    public function publicStatus(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->encryption,
            'mailbox' => $this->mailbox,
            'configured' => $this->configured(),
            'missing' => $this->missing(),
        ];
    }
}

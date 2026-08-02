<?php

declare(strict_types=1);

namespace Katakata\Email\Import;

use RuntimeException;

final class MobileconfigAccountImporter
{
    public function __construct(private readonly SafePlistParser $parser)
    {
    }

    /** @return list<ImportedMailboxAccount> */
    public function import(string $contents): array
    {
        $profile = $this->parser->parse($contents);
        if (!is_array($profile)) {
            throw new RuntimeException('Configuration profile root must be a dictionary.');
        }

        $payloads = $profile['PayloadContent'] ?? [];
        if (!is_array($payloads)) {
            throw new RuntimeException('Configuration profile payload list is invalid.');
        }

        $accounts = [];
        foreach ($payloads as $payload) {
            if (!is_array($payload)) {
                continue;
            }
            $type = (string) ($payload['PayloadType'] ?? '');
            if ($type !== 'com.apple.mail.managed') {
                continue;
            }
            if ((string) ($payload['EmailAccountType'] ?? '') !== 'EmailTypeIMAP') {
                continue;
            }
            if ($this->containsIdentityMaterial($payload)) {
                throw new RuntimeException('Mail profile requires certificate or identity material and cannot be imported automatically.');
            }

            $host = trim((string) ($payload['IncomingMailServerHostName'] ?? ''));
            if ($host === '') {
                continue;
            }
            $ssl = (bool) ($payload['IncomingMailServerUseSSL'] ?? false);
            if (!$ssl) {
                throw new RuntimeException('Only direct-TLS IMAP profiles can be imported.');
            }

            $accounts[] = new ImportedMailboxAccount(
                label: trim((string) ($payload['EmailAccountDescription'] ?? $payload['EmailAddress'] ?? 'Mailbox')) ?: 'Mailbox',
                emailAddress: trim((string) ($payload['EmailAddress'] ?? '')),
                incomingHost: $host,
                incomingPort: (int) ($payload['IncomingMailServerPortNumber'] ?? 993),
                incomingEncryption: 'ssl',
                incomingUsername: trim((string) ($payload['IncomingMailServerUsername'] ?? '')),
                incomingMailbox: 'INBOX',
                outgoingHost: $this->nullableString($payload['OutgoingMailServerHostName'] ?? null),
                outgoingPort: isset($payload['OutgoingMailServerPortNumber']) ? (int) $payload['OutgoingMailServerPortNumber'] : null,
                outgoingEncryption: isset($payload['OutgoingMailServerUseSSL'])
                    ? ((bool) $payload['OutgoingMailServerUseSSL'] ? 'ssl' : 'none')
                    : null,
                outgoingUsername: $this->nullableString($payload['OutgoingMailServerUsername'] ?? null),
                embeddedCredentialDetected: $this->embeddedCredentialDetected($payload),
                warnings: $this->warnings($payload),
            );
        }

        if ($accounts === []) {
            throw new RuntimeException('No supported IMAP Mail payload was found.');
        }
        return $accounts;
    }

    /** @param array<string,mixed> $payload */
    private function embeddedCredentialDetected(array $payload): bool
    {
        foreach (['IncomingPassword', 'OutgoingPassword', 'IncomingMailServerPassword', 'OutgoingMailServerPassword'] as $key) {
            if (isset($payload[$key]) && (string) $payload[$key] !== '') {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $payload */
    private function containsIdentityMaterial(array $payload): bool
    {
        foreach (array_keys($payload) as $key) {
            if (stripos((string) $key, 'certificate') !== false || stripos((string) $key, 'identity') !== false) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $payload @return list<string> */
    private function warnings(array $payload): array
    {
        $warnings = [];
        if ($this->embeddedCredentialDetected($payload)) {
            $warnings[] = 'Embedded credential detected; it will not be persisted.';
        }
        if (isset($payload['OutgoingMailServerHostName'])) {
            $warnings[] = 'Outgoing mail settings are shown for review but are not imported.';
        }
        return $warnings;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}

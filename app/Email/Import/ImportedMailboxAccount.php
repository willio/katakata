<?php

declare(strict_types=1);

namespace Katakata\Email\Import;

final readonly class ImportedMailboxAccount
{
    /** @param list<string> $warnings */
    public function __construct(
        public string $label,
        public string $emailAddress,
        public string $incomingHost,
        public int $incomingPort,
        public string $incomingEncryption,
        public string $incomingUsername,
        public string $incomingMailbox,
        public ?string $outgoingHost = null,
        public ?int $outgoingPort = null,
        public ?string $outgoingEncryption = null,
        public ?string $outgoingUsername = null,
        public bool $embeddedCredentialDetected = false,
        public array $warnings = [],
    ) {
    }

    public function suggestedId(): string
    {
        $base = strtolower(trim($this->label));
        $base = preg_replace('/[^a-z0-9_-]+/', '-', $base) ?? 'mailbox';
        $base = trim($base, '-_');
        if (strlen($base) < 2) {
            $base = 'mailbox';
        }
        return substr($base, 0, 32);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'email_address' => $this->emailAddress,
            'incoming_host' => $this->incomingHost,
            'incoming_port' => $this->incomingPort,
            'incoming_encryption' => $this->incomingEncryption,
            'incoming_username' => $this->incomingUsername,
            'incoming_mailbox' => $this->incomingMailbox,
            'outgoing_host' => $this->outgoingHost,
            'outgoing_port' => $this->outgoingPort,
            'outgoing_encryption' => $this->outgoingEncryption,
            'outgoing_username' => $this->outgoingUsername,
            'embedded_credential_detected' => $this->embeddedCredentialDetected,
            'warnings' => $this->warnings,
        ];
    }
}

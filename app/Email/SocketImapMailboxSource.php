<?php

declare(strict_types=1);

namespace Katakata\Email;

use Closure;
use RuntimeException;

final class SocketImapMailboxSource implements ImapMailboxSource
{
    /** @var Closure(ImapSettings):ImapWireConnection */
    private Closure $connections;

    /** @param null|Closure(ImapSettings):ImapWireConnection $connections */
    public function __construct(
        private readonly MailTextExtractor $extractor,
        ?Closure $connections = null,
    ) {
        $this->connections = $connections ?? static fn (ImapSettings $settings): ImapWireConnection => new TlsImapWireConnection($settings);
    }

    public function fetch(ImapSettings $settings, int $limit = 100): array
    {
        if (!$settings->configured()) {
            throw new RuntimeException('The IMAP TLS connection is not configured.');
        }
        foreach ([$settings->username, $settings->password, $settings->mailbox] as $value) {
            if (str_contains($value, "\r") || str_contains($value, "\n")) {
                throw new RuntimeException('The IMAP configuration contains invalid control characters.');
            }
        }

        $wire = ($this->connections)($settings);
        try {
            $wire->command('LOGIN ' . $this->quote($settings->username) . ' ' . $this->quote($settings->password));
            $wire->command('SELECT ' . $this->quote($settings->mailbox));
            $search = $wire->command('UID SEARCH ALL');
            $uids = $this->uids($search);
            rsort($uids, SORT_NUMERIC);
            $uids = array_slice($uids, 0, max(1, $limit));

            $messages = [];
            foreach ($uids as $uid) {
                $response = $wire->command('UID FETCH ' . $uid . ' (BODY.PEEK[])');
                $raw = $this->literal($response);
                if ($raw === null) {
                    continue;
                }
                $message = $this->extractor->extract($raw);
                $messages[] = [
                    'id' => 'uid-' . $uid,
                    'from' => $message['from'],
                    'to' => $message['to'],
                    'subject' => $message['subject'],
                    'text' => $message['text'],
                    'html' => null,
                    'received_at' => $message['received_at'],
                    'attachments' => [],
                ];
            }
            return $messages;
        } finally {
            $wire->close();
        }
    }

    /** @param list<string> $response @return list<int> */
    private function uids(array $response): array
    {
        foreach ($response as $line) {
            if (preg_match('/^\* SEARCH(?:\s+(.*))?\r?\n?$/', trim($line), $match) !== 1) {
                continue;
            }
            $value = trim((string) ($match[1] ?? ''));
            if ($value === '') {
                return [];
            }
            return array_values(array_map('intval', preg_split('/\s+/', $value) ?: []));
        }
        return [];
    }

    /** @param list<string> $response */
    private function literal(array $response): ?string
    {
        foreach ($response as $index => $line) {
            if (preg_match('/\{(\d+)\}\r\n$/', $line, $match) === 1) {
                $literal = $response[$index + 1] ?? null;
                return is_string($literal) && strlen($literal) === (int) $match[1] ? $literal : null;
            }
        }
        return null;
    }

    private function quote(string $value): string
    {
        return '"' . addcslashes($value, "\\\"") . '"';
    }
}

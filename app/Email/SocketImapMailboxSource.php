<?php

declare(strict_types=1);

namespace Katakata\Email;

use Closure;
use RuntimeException;

final class SocketImapMailboxSource implements ImapMailboxSource
{
    private const MAX_TEXT_BYTES = 1048576;

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
            $uids = $this->uids($wire->command('UID SEARCH ALL'));
            rsort($uids, SORT_NUMERIC);
            $uids = array_slice($uids, 0, max(1, $limit));

            $messages = [];
            foreach ($uids as $uid) {
                $structure = $this->responseText($wire->command('UID FETCH ' . $uid . ' (BODYSTRUCTURE)'));
                $part = $this->textPart($structure);
                if ($part === null) {
                    continue;
                }

                $headerResponse = $wire->command(
                    'UID FETCH ' . $uid . ' (BODY.PEEK[HEADER.FIELDS (FROM TO SUBJECT DATE)])',
                );
                $headers = $this->literal($headerResponse);
                if ($headers === null) {
                    continue;
                }

                $bodyResponse = $wire->command(
                    'UID FETCH ' . $uid . ' (BODY.PEEK[' . $part['path'] . ']<0.' . self::MAX_TEXT_BYTES . '>)',
                );
                $body = $this->literal($bodyResponse);
                if ($body === null) {
                    continue;
                }

                $contentType = 'text/plain'
                    . ($part['charset'] !== null ? '; charset=' . $part['charset'] : '');
                $raw = rtrim($headers, "\r\n")
                    . "\r\nContent-Type: " . $contentType
                    . "\r\nContent-Transfer-Encoding: " . $part['encoding']
                    . "\r\n\r\n" . $body;
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

    /** @return array{path:string,encoding:string,charset:?string}|null */
    private function textPart(string $response): ?array
    {
        $position = stripos($response, 'BODYSTRUCTURE');
        if ($position === false) {
            return null;
        }
        $start = strpos($response, '(', $position);
        if ($start === false) {
            return null;
        }

        $offset = $start;
        $tree = $this->parseValue($response, $offset);
        return is_array($tree) ? $this->findTextPart($tree) : null;
    }

    /** @param list<mixed> $node @return array{path:string,encoding:string,charset:?string}|null */
    private function findTextPart(array $node, string $path = ''): ?array
    {
        if (isset($node[0]) && is_array($node[0])) {
            $index = 1;
            foreach ($node as $child) {
                if (!is_array($child)) {
                    break;
                }
                $found = $this->findTextPart($child, $path === '' ? (string) $index : $path . '.' . $index);
                if ($found !== null) {
                    return $found;
                }
                $index++;
            }
            return null;
        }

        $type = strtoupper((string) ($node[0] ?? ''));
        $subtype = strtoupper((string) ($node[1] ?? ''));
        if ($type !== 'TEXT' || $subtype !== 'PLAIN' || $this->isAttachment($node)) {
            return null;
        }

        $parameters = is_array($node[2] ?? null) ? $node[2] : [];
        $charset = null;
        for ($i = 0, $count = count($parameters); $i + 1 < $count; $i += 2) {
            if (strtoupper((string) $parameters[$i]) === 'CHARSET') {
                $charset = (string) $parameters[$i + 1];
                break;
            }
        }

        return [
            'path' => $path !== '' ? $path : '1',
            'encoding' => strtolower((string) ($node[5] ?? '7bit')),
            'charset' => $charset,
        ];
    }

    /** @param list<mixed> $node */
    private function isAttachment(array $node): bool
    {
        foreach (array_slice($node, 7) as $extension) {
            if (!is_array($extension) || $extension === []) {
                continue;
            }
            if (strtoupper((string) ($extension[0] ?? '')) === 'ATTACHMENT') {
                return true;
            }
        }
        return false;
    }

    private function parseValue(string $input, int &$offset): mixed
    {
        $this->skipSpaces($input, $offset);
        if (($input[$offset] ?? '') === '(') {
            $offset++;
            $values = [];
            while ($offset < strlen($input)) {
                $this->skipSpaces($input, $offset);
                if (($input[$offset] ?? '') === ')') {
                    $offset++;
                    return $values;
                }
                $values[] = $this->parseValue($input, $offset);
            }
            return $values;
        }

        if (($input[$offset] ?? '') === '"') {
            $offset++;
            $value = '';
            while ($offset < strlen($input)) {
                $character = $input[$offset++];
                if ($character === '\\' && $offset < strlen($input)) {
                    $value .= $input[$offset++];
                    continue;
                }
                if ($character === '"') {
                    return $value;
                }
                $value .= $character;
            }
            return $value;
        }

        $start = $offset;
        while ($offset < strlen($input) && !str_contains(" ()\r\n\t", $input[$offset])) {
            $offset++;
        }
        $value = substr($input, $start, $offset - $start);
        return strtoupper($value) === 'NIL' ? null : $value;
    }

    private function skipSpaces(string $input, int &$offset): void
    {
        while ($offset < strlen($input) && str_contains(" \r\n\t", $input[$offset])) {
            $offset++;
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
            return $value === '' ? [] : array_values(array_map('intval', preg_split('/\s+/', $value) ?: []));
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

    /** @param list<string> $response */
    private function responseText(array $response): string
    {
        return implode('', $response);
    }

    private function quote(string $value): string
    {
        return '"' . addcslashes($value, "\\\"") . '"';
    }
}

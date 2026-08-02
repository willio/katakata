<?php

declare(strict_types=1);

namespace Katakata\Email;

use DateTimeImmutable;
use RuntimeException;

final class NativeImapMailboxSource implements ImapMailboxSource
{
    public function fetch(ImapSettings $settings, int $limit = 100): array
    {
        if (!function_exists('imap_open')) {
            throw new RuntimeException('The PHP IMAP extension is not installed.');
        }

        $flags = match ($settings->encryption) {
            'ssl' => '/imap/ssl',
            'tls' => '/imap/tls',
            'none', '' => '/imap/notls',
            default => throw new RuntimeException('IMAP_ENCRYPTION must be ssl, tls, or none.'),
        };
        $mailbox = sprintf('{%s:%d%s}%s', $settings->host, $settings->port, $flags, $settings->mailbox);
        $stream = @imap_open($mailbox, $settings->username, $settings->password, OP_READONLY);
        if ($stream === false) {
            throw new RuntimeException('Unable to connect to the configured IMAP mailbox.');
        }

        try {
            $numbers = imap_search($stream, 'ALL', SE_UID) ?: [];
            rsort($numbers, SORT_NUMERIC);
            $numbers = array_slice($numbers, 0, max(1, $limit));
            $messages = [];
            foreach ($numbers as $uid) {
                $overview = imap_fetch_overview($stream, (string) $uid, FT_UID)[0] ?? null;
                if (!is_object($overview)) {
                    continue;
                }
                $structure = imap_fetchstructure($stream, (string) $uid, FT_UID);
                [$text, $html, $attachments] = $this->content($stream, (int) $uid, $structure);
                $messageId = isset($overview->message_id) && trim((string) $overview->message_id) !== ''
                    ? hash('sha256', trim((string) $overview->message_id))
                    : 'uid-' . (int) $uid;
                $messages[] = [
                    'id' => $messageId,
                    'from' => $this->decode((string) ($overview->from ?? '')),
                    'to' => $this->decode((string) ($overview->to ?? '')),
                    'subject' => $this->decode((string) ($overview->subject ?? '')),
                    'text' => $text,
                    'html' => $html,
                    'received_at' => (new DateTimeImmutable((string) ($overview->date ?? 'now')))->format(DATE_ATOM),
                    'attachments' => $attachments,
                ];
            }
            return $messages;
        } finally {
            imap_close($stream);
        }
    }

    /** @return array{0:string,1:?string,2:list<array{id:string,name:string,media_type:string,content:string}>} */
    private function content(mixed $stream, int $uid, mixed $structure): array
    {
        if (!is_object($structure) || !isset($structure->parts) || !is_array($structure->parts)) {
            $body = (string) imap_body($stream, (string) $uid, FT_UID | FT_PEEK);
            return [trim($body), null, []];
        }

        $text = '';
        $html = null;
        $attachments = [];
        foreach ($structure->parts as $index => $part) {
            if (!is_object($part)) {
                continue;
            }
            $section = (string) ($index + 1);
            $raw = (string) imap_fetchbody($stream, (string) $uid, $section, FT_UID | FT_PEEK);
            $decoded = $this->decodeTransfer($raw, (int) ($part->encoding ?? 0));
            $name = $this->parameter($part, 'filename') ?? $this->parameter($part, 'name');
            if ($name !== null) {
                $attachments[] = [
                    'id' => 'part-' . str_replace('.', '-', $section),
                    'name' => $this->decode($name),
                    'media_type' => $this->mediaType($part),
                    'content' => $decoded,
                ];
                continue;
            }
            $subtype = strtoupper((string) ($part->subtype ?? ''));
            if ($subtype === 'PLAIN' && $text === '') {
                $text = trim($decoded);
            } elseif ($subtype === 'HTML' && $html === null) {
                $html = $decoded;
            }
        }

        if ($text === '' && $html !== null) {
            $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        return [$text, $html, $attachments];
    }

    private function parameter(object $part, string $name): ?string
    {
        foreach (['dparameters', 'parameters'] as $property) {
            foreach ((array) ($part->{$property} ?? []) as $parameter) {
                if (is_object($parameter) && strtolower((string) ($parameter->attribute ?? '')) === $name) {
                    return (string) ($parameter->value ?? '');
                }
            }
        }
        return null;
    }

    private function decodeTransfer(string $value, int $encoding): string
    {
        return match ($encoding) {
            3 => base64_decode($value, true) ?: '',
            4 => quoted_printable_decode($value),
            default => $value,
        };
    }

    private function decode(string $value): string
    {
        if (!function_exists('imap_mime_header_decode')) {
            return $value;
        }
        $parts = imap_mime_header_decode($value);
        return implode('', array_map(static fn (object $part): string => (string) ($part->text ?? ''), $parts));
    }

    private function mediaType(object $part): string
    {
        $types = ['text', 'multipart', 'message', 'application', 'audio', 'image', 'video', 'other'];
        $type = $types[(int) ($part->type ?? 7)] ?? 'application';
        return $type . '/' . strtolower((string) ($part->subtype ?? 'octet-stream'));
    }
}

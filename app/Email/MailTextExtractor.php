<?php

declare(strict_types=1);

namespace Katakata\Email;

use DateTimeImmutable;

final class MailTextExtractor
{
    /** @return array{from:string,to:string,subject:string,received_at:string,text:string} */
    public function extract(string $raw): array
    {
        [$headerBlock, $body] = $this->split($raw);
        $headers = $this->headers($headerBlock);
        $contentType = strtolower($headers['content-type'] ?? 'text/plain');
        $encoding = strtolower($headers['content-transfer-encoding'] ?? '');
        $text = str_starts_with($contentType, 'multipart/')
            ? $this->multipartText($body, $contentType)
            : $this->decodeBody($body, $encoding, $contentType);

        return [
            'from' => $this->decodeHeader($headers['from'] ?? ''),
            'to' => $this->decodeHeader($headers['to'] ?? ''),
            'subject' => $this->decodeHeader($headers['subject'] ?? ''),
            'received_at' => $this->date($headers['date'] ?? ''),
            'text' => trim($text),
        ];
    }

    /** @return array{0:string,1:string} */
    private function split(string $raw): array
    {
        $parts = preg_split("/\r?\n\r?\n/", $raw, 2);
        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    /** @return array<string,string> */
    private function headers(string $block): array
    {
        $block = preg_replace("/\r?\n[\t ]+/", ' ', $block) ?? $block;
        $headers = [];
        foreach (preg_split('/\r?\n/', $block) ?: [] as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }
        return $headers;
    }

    private function multipartText(string $body, string $contentType): string
    {
        if (preg_match('/boundary=(?:"([^"]+)"|([^;\s]+))/i', $contentType, $match) !== 1) {
            return '';
        }
        $boundary = $match[1] !== '' ? $match[1] : $match[2];
        foreach (explode('--' . $boundary, $body) as $part) {
            [$headers, $payload] = $this->split(ltrim($part, "\r\n"));
            $map = $this->headers($headers);
            $type = strtolower($map['content-type'] ?? 'text/plain');
            $disposition = strtolower($map['content-disposition'] ?? '');
            if (str_contains($disposition, 'attachment') || !str_starts_with($type, 'text/plain')) {
                continue;
            }
            return $this->decodeBody($payload, strtolower($map['content-transfer-encoding'] ?? ''), $type);
        }
        return '';
    }

    private function decodeBody(string $body, string $encoding, string $contentType): string
    {
        $decoded = match ($encoding) {
            'base64' => base64_decode(preg_replace('/\s+/', '', $body) ?? $body, true) ?: '',
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
        if (preg_match('/charset=(?:"([^"]+)"|([^;\s]+))/i', $contentType, $match) === 1) {
            $charset = $match[1] !== '' ? $match[1] : $match[2];
            if (strcasecmp($charset, 'UTF-8') !== 0 && function_exists('iconv')) {
                $converted = @iconv($charset, 'UTF-8//IGNORE', $decoded);
                if (is_string($converted)) {
                    $decoded = $converted;
                }
            }
        }
        return $decoded;
    }

    private function decodeHeader(string $value): string
    {
        if (function_exists('mb_decode_mimeheader')) {
            return mb_decode_mimeheader($value);
        }
        return $value;
    }

    private function date(string $value): string
    {
        try {
            return new DateTimeImmutable($value !== '' ? $value : 'now')->format(DATE_ATOM);
        } catch (\Throwable) {
            return (new DateTimeImmutable())->format(DATE_ATOM);
        }
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Distribution;

use Closure;
use RuntimeException;

final class ResendEmailTransport implements EmailTransport
{
    private const ENDPOINT = 'https://api.resend.com/emails';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $from,
        private readonly ?Closure $sender = null,
    ) {
        if (trim($this->apiKey) === '' || trim($this->from) === '') {
            throw new RuntimeException('Resend transport requires RESEND_API_KEY and MAIL_FROM.');
        }
    }

    public function send(EmailMessage $message, string $idempotencyKey): array
    {
        $payload = json_encode([
            'from' => $this->from,
            'to' => [$message->to],
            'subject' => $message->subject,
            'html' => $message->html,
            'text' => $message->text,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Idempotency-Key: ' . substr($idempotencyKey, 0, 256),
        ];

        if ($this->sender !== null) {
            $response = ($this->sender)(self::ENDPOINT, $payload, $headers);
        } else {
            $context = stream_context_create(['http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $payload,
                'ignore_errors' => true,
                'timeout' => 15,
            ]]);
            $body = @file_get_contents(self::ENDPOINT, false, $context);
            $response = [
                'status' => $this->status($http_response_header ?? []),
                'body' => $body === false ? '' : $body,
            ];
        }

        $status = (int) ($response['status'] ?? 0);
        $body = (string) ($response['body'] ?? '');
        $data = json_decode($body, true);
        if ($status < 200 || $status >= 300) {
            $detail = is_array($data) ? (string) ($data['message'] ?? $data['name'] ?? '') : '';
            throw new RuntimeException('Resend delivery failed with HTTP ' . $status . ($detail !== '' ? ': ' . $detail : '.'));
        }
        if (!is_array($data) || !is_string($data['id'] ?? null) || $data['id'] === '') {
            throw new RuntimeException('Resend returned an invalid delivery response.');
        }

        return ['provider' => 'resend', 'id' => $data['id']];
    }

    /** @param list<string> $headers */
    private function status(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }
        return 0;
    }
}

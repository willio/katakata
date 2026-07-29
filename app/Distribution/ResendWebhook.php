<?php

declare(strict_types=1);

namespace Katakata\Distribution;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use Katakata\Editorial\AtomicFile;
use RuntimeException;

final class ResendWebhook
{
    private const EVENTS = [
        'email.sent',
        'email.delivered',
        'email.delivery_delayed',
        'email.bounced',
        'email.complained',
        'email.failed',
        'email.suppressed',
    ];

    public function __construct(
        private readonly string $path,
        private readonly string $secret,
        private readonly SubscriberStore $subscribers,
        private readonly AtomicFile $files,
        private readonly int $tolerance = 300,
    ) {
        if (!str_starts_with($secret, 'whsec_')) {
            throw new RuntimeException('RESEND_WEBHOOK_SECRET must be configured.');
        }
    }

    /**
     * @param array{svix-id?: string, svix-timestamp?: string, svix-signature?: string} $headers
     * @return array{duplicate: bool, type: string, provider_id: string, suppressed: int}
     */
    public function handle(string $payload, array $headers, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();
        $id = trim((string) ($headers['svix-id'] ?? ''));
        $timestamp = trim((string) ($headers['svix-timestamp'] ?? ''));
        $signature = trim((string) ($headers['svix-signature'] ?? ''));
        $this->verify($payload, $id, $timestamp, $signature, $now);

        try {
            $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Resend webhook payload is invalid.', 0, $error);
        }
        if (!is_array($event)) {
            throw new InvalidArgumentException('Resend webhook payload is invalid.');
        }

        $type = (string) ($event['type'] ?? '');
        $data = $event['data'] ?? null;
        if (!in_array($type, self::EVENTS, true) || !is_array($data)) {
            throw new InvalidArgumentException('Resend webhook event is unsupported.');
        }

        $providerId = trim((string) ($data['email_id'] ?? ''));
        $recipients = $this->recipients($data['to'] ?? null);
        if ($providerId === '' || $recipients === []) {
            throw new InvalidArgumentException('Resend webhook event is incomplete.');
        }

        $eventPath = $this->path . '/' . hash('sha256', $id) . '.json';
        if (is_file($eventPath)) {
            return [
                'duplicate' => true,
                'type' => $type,
                'provider_id' => $providerId,
                'suppressed' => 0,
            ];
        }

        $suppressed = 0;
        if (in_array($type, ['email.bounced', 'email.complained'], true)) {
            foreach ($recipients as $email) {
                $suppressed += $this->subscribers->suppress($email, $type, $now) ? 1 : 0;
            }
        }

        $this->files->write($eventPath, json_encode([
            'version' => 1,
            'svix_id' => $id,
            'type' => $type,
            'provider_id' => $providerId,
            'recipient_hashes' => array_map(static fn (string $email): string => hash('sha256', $email), $recipients),
            'event_created_at' => (string) ($event['created_at'] ?? ''),
            'received_at' => $now->format(DATE_ATOM),
            'suppressed' => $suppressed,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        @chmod($eventPath, 0600);

        return [
            'duplicate' => false,
            'type' => $type,
            'provider_id' => $providerId,
            'suppressed' => $suppressed,
        ];
    }

    private function verify(
        string $payload,
        string $id,
        string $timestamp,
        string $signatures,
        DateTimeImmutable $now,
    ): void {
        if ($id === '' || preg_match('/^\d+$/', $timestamp) !== 1 || $signatures === '') {
            throw new InvalidArgumentException('Resend webhook signature is missing.');
        }
        if (abs($now->getTimestamp() - (int) $timestamp) > $this->tolerance) {
            throw new InvalidArgumentException('Resend webhook timestamp is outside the allowed window.');
        }

        $encodedSecret = substr($this->secret, strlen('whsec_'));
        $encodedSecret .= str_repeat('=', (4 - strlen($encodedSecret) % 4) % 4);
        $key = base64_decode($encodedSecret, true);
        if ($key === false || $key === '') {
            throw new RuntimeException('RESEND_WEBHOOK_SECRET is invalid.');
        }

        $expected = base64_encode(hash_hmac(
            'sha256',
            $id . '.' . $timestamp . '.' . $payload,
            $key,
            true,
        ));
        foreach (preg_split('/\s+/', $signatures) ?: [] as $candidate) {
            if (str_starts_with($candidate, 'v1,') && hash_equals($expected, substr($candidate, 3))) {
                return;
            }
        }

        throw new InvalidArgumentException('Resend webhook signature is invalid.');
    }

    /** @return list<string> */
    private function recipients(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $recipients = [];
        foreach ($value as $email) {
            if (!is_string($email)) {
                continue;
            }
            $email = strtolower(trim($email));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = $email;
            }
        }

        return array_values(array_unique($recipients));
    }
}

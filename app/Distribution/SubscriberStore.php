<?php

declare(strict_types=1);

namespace Katakata\Distribution;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use Katakata\Editorial\AtomicFile;
use RuntimeException;

final class SubscriberStore
{
    public function __construct(
        private readonly string $path,
        private readonly string $secret,
        private readonly AtomicFile $files,
    ) {
        if ($secret === '') {
            throw new InvalidArgumentException('NEWSLETTER_SECRET must be configured.');
        }
    }

    /** @return array{email: string, confirmation_token: string, expires_at: string} */
    public function request(string $email, ?DateTimeImmutable $now = null): array
    {
        $email = $this->email($email);
        $now ??= new DateTimeImmutable();
        $data = $this->read();
        $id = hash('sha256', $email);
        $existing = $data['subscribers'][$id] ?? null;

        if (is_array($existing) && ($existing['status'] ?? null) === 'active') {
            throw new RuntimeException('This email address is already subscribed.');
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = $now->add(new DateInterval('P2D'));
        $data['subscribers'][$id] = [
            'email' => $email,
            'status' => 'pending',
            'requested_at' => $now->format(DateTimeImmutable::ATOM),
            'confirmed_at' => null,
            'unsubscribed_at' => null,
        ];
        $data['confirmations'][hash('sha256', $token)] = [
            'subscriber_id' => $id,
            'expires_at' => $expiresAt->format(DateTimeImmutable::ATOM),
        ];
        $this->write($data);

        return [
            'email' => $email,
            'confirmation_token' => $token,
            'expires_at' => $expiresAt->format(DateTimeImmutable::ATOM),
        ];
    }

    /** @return array{email: string, status: string, unsubscribe_token: string} */
    public function confirm(string $token, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();
        $data = $this->read();
        $key = hash('sha256', $token);
        $confirmation = $data['confirmations'][$key] ?? null;

        if (
            !is_array($confirmation)
            || new DateTimeImmutable((string) ($confirmation['expires_at'] ?? 'now')) < $now
        ) {
            throw new RuntimeException('Newsletter confirmation is invalid or expired.');
        }

        $id = (string) ($confirmation['subscriber_id'] ?? '');
        $subscriber = $data['subscribers'][$id] ?? null;
        if (!is_array($subscriber)) {
            throw new RuntimeException('Newsletter subscriber was not found.');
        }

        $subscriber['status'] = 'active';
        $subscriber['confirmed_at'] = $now->format(DateTimeImmutable::ATOM);
        $subscriber['unsubscribed_at'] = null;
        $data['subscribers'][$id] = $subscriber;
        unset($data['confirmations'][$key]);
        $this->write($data);

        return [
            'email' => (string) $subscriber['email'],
            'status' => 'active',
            'unsubscribe_token' => $this->unsubscribeToken($id, (string) $subscriber['email']),
        ];
    }

    /** @return array{email: string, status: string} */
    public function unsubscribe(string $token, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();
        $data = $this->read();

        foreach ($data['subscribers'] as $id => $subscriber) {
            if (
                is_array($subscriber)
                && hash_equals($this->unsubscribeToken((string) $id, (string) ($subscriber['email'] ?? '')), $token)
            ) {
                $subscriber['status'] = 'unsubscribed';
                $subscriber['unsubscribed_at'] = $now->format(DateTimeImmutable::ATOM);
                $data['subscribers'][$id] = $subscriber;
                $this->write($data);

                return ['email' => (string) $subscriber['email'], 'status' => 'unsubscribed'];
            }
        }

        throw new RuntimeException('Newsletter unsubscribe token is invalid.');
    }

    /** @return list<array{email: string, status: string, requested_at: string, confirmed_at: ?string}> */
    public function active(): array
    {
        $active = [];
        foreach ($this->read()['subscribers'] as $subscriber) {
            if (!is_array($subscriber) || ($subscriber['status'] ?? null) !== 'active') {
                continue;
            }
            $active[] = [
                'email' => (string) $subscriber['email'],
                'status' => 'active',
                'requested_at' => (string) $subscriber['requested_at'],
                'confirmed_at' => isset($subscriber['confirmed_at']) ? (string) $subscriber['confirmed_at'] : null,
            ];
        }

        usort($active, static fn (array $a, array $b): int => strcmp($a['email'], $b['email']));
        return $active;
    }

    private function email(string $email): string
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid email address is required.');
        }

        return $email;
    }

    private function unsubscribeToken(string $id, string $email): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $id . "\n" . $email, $this->secret, true)), '+/', '-_'), '=');
    }

    /** @return array{subscribers: array<string, mixed>, confirmations: array<string, mixed>} */
    private function read(): array
    {
        if (!is_file($this->path)) {
            return ['subscribers' => [], 'confirmations' => []];
        }

        $decoded = json_decode((string) file_get_contents($this->path), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Newsletter subscriber store is invalid.');
        }

        return [
            'subscribers' => is_array($decoded['subscribers'] ?? null) ? $decoded['subscribers'] : [],
            'confirmations' => is_array($decoded['confirmations'] ?? null) ? $decoded['confirmations'] : [],
        ];
    }

    /** @param array<string, mixed> $data */
    private function write(array $data): void
    {
        $this->files->write(
            $this->path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        );
        @chmod($this->path, 0600);
    }
}

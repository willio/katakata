<?php

declare(strict_types=1);

namespace Katakata\Distribution;

use DateInterval;
use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;
use RuntimeException;

final class MailQueue
{
    public function __construct(
        private readonly string $path,
        private readonly EmailTransport $transport,
        private readonly AtomicFile $files,
    ) {
    }

    public function enqueue(string $idempotencyKey, EmailMessage $message, ?DateTimeImmutable $now = null): string
    {
        $id = hash('sha256', $idempotencyKey);
        $path = $this->path . '/' . $id . '.json';
        if (is_file($path)) {
            return $id;
        }

        $now ??= new DateTimeImmutable();
        $this->write($path, [
            'version' => 1,
            'id' => $id,
            'idempotency_key' => $idempotencyKey,
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => $now->format(DATE_ATOM),
            'next_attempt_at' => $now->format(DATE_ATOM),
            'delivered_at' => null,
            'last_error' => null,
            'message' => $message->toArray(),
        ]);

        return $id;
    }

    /** @return array{processed: int, delivered: int, failed: int} */
    public function work(int $limit = 50, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();
        $result = ['processed' => 0, 'delivered' => 0, 'failed' => 0];
        foreach (glob($this->path . '/*.json') ?: [] as $path) {
            if ($result['processed'] >= max(1, $limit)) {
                break;
            }

            $attempt = $this->read($path);
            if (($attempt['status'] ?? null) === 'delivered') {
                continue;
            }
            $dueAt = new DateTimeImmutable((string) ($attempt['next_attempt_at'] ?? 'now'));
            if ($dueAt > $now) {
                continue;
            }

            $result['processed']++;
            $attempts = (int) ($attempt['attempts'] ?? 0) + 1;
            try {
                $message = $attempt['message'] ?? [];
                $this->transport->send(new EmailMessage(
                    (string) ($message['to'] ?? ''),
                    (string) ($message['subject'] ?? ''),
                    (string) ($message['html'] ?? ''),
                    (string) ($message['text'] ?? ''),
                ), (string) $attempt['idempotency_key']);
                $attempt['status'] = 'delivered';
                $attempt['attempts'] = $attempts;
                $attempt['delivered_at'] = $now->format(DATE_ATOM);
                $attempt['last_error'] = null;
                $result['delivered']++;
            } catch (\Throwable $error) {
                $delay = min(3600, 60 * (2 ** min(6, $attempts - 1)));
                $attempt['status'] = 'failed';
                $attempt['attempts'] = $attempts;
                $attempt['last_error'] = $error->getMessage();
                $attempt['next_attempt_at'] = $now->add(new DateInterval('PT' . $delay . 'S'))->format(DATE_ATOM);
                $result['failed']++;
            }
            $this->write($path, $attempt);
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function read(string $path): array
    {
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            throw new RuntimeException("Mail queue item [{$path}] is invalid.");
        }
        return $data;
    }

    /** @param array<string, mixed> $data */
    private function write(string $path, array $data): void
    {
        $this->files->write($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        @chmod($path, 0600);
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Distribution;

use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;

final class FilesystemEmailTransport implements EmailTransport
{
    public function __construct(
        private readonly string $path,
        private readonly AtomicFile $files,
    ) {
    }

    public function send(EmailMessage $message, string $idempotencyKey): array
    {
        $path = $this->path . '/' . hash('sha256', $idempotencyKey) . '.json';
        $this->files->write($path, json_encode([
            'idempotency_key' => $idempotencyKey,
            'sent_at' => (new DateTimeImmutable())->format(DATE_ATOM),
            'message' => $message->toArray(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");

        return ['path' => $path];
    }
}

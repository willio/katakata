<?php

declare(strict_types=1);

namespace Katakata\Email\Import;

use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;
use RuntimeException;

final class MailProfileImportStore
{
    private const TTL_SECONDS = 900;

    public function __construct(
        private readonly string $path,
        private readonly AtomicFile $files,
    ) {
    }

    /** @param list<ImportedMailboxAccount> $candidates */
    public function create(array $candidates, ?DateTimeImmutable $now = null): string
    {
        $now ??= new DateTimeImmutable();
        $token = bin2hex(random_bytes(24));
        $payload = [
            'created_at' => $now->format(DATE_ATOM),
            'expires_at' => $now->modify('+' . self::TTL_SECONDS . ' seconds')->format(DATE_ATOM),
            'candidates' => array_map(
                static fn (ImportedMailboxAccount $candidate): array => $candidate->toArray(),
                $candidates,
            ),
        ];
        $target = $this->file($token);
        $this->files->write($target, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        @chmod($target, 0600);
        return $token;
    }

    /** @return array<string,mixed>|null */
    public function consume(string $token, int $index, ?DateTimeImmutable $now = null): ?array
    {
        $now ??= new DateTimeImmutable();
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            return null;
        }
        $target = $this->file($token);
        if (!is_file($target)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($target), true);
        @unlink($target);
        if (!is_array($data)) {
            return null;
        }
        try {
            if (new DateTimeImmutable((string) ($data['expires_at'] ?? '')) < $now) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }
        $candidate = $data['candidates'][$index] ?? null;
        return is_array($candidate) ? $candidate : null;
    }

    public function prune(?DateTimeImmutable $now = null): void
    {
        $now ??= new DateTimeImmutable();
        foreach (glob(rtrim($this->path, '/') . '/*.json') ?: [] as $target) {
            $data = json_decode((string) file_get_contents($target), true);
            try {
                $expires = is_array($data) ? new DateTimeImmutable((string) ($data['expires_at'] ?? '')) : null;
            } catch (\Throwable) {
                $expires = null;
            }
            if ($expires === null || $expires < $now) {
                @unlink($target);
            }
        }
    }

    private function file(string $token): string
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            throw new RuntimeException('Mail profile import token is invalid.');
        }
        return rtrim($this->path, '/') . '/' . $token . '.json';
    }
}

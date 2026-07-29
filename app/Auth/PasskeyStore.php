<?php

declare(strict_types=1);

namespace Katakata\Auth;

use Katakata\Editorial\AtomicFile;
use RuntimeException;

final class PasskeyStore
{
    public function __construct(
        private readonly string $path,
        private readonly AtomicFile $files,
    ) {
    }

    /** @param array<string, mixed> $credential */
    public function add(string $accountId, array $credential): void
    {
        $data = $this->read();
        $id = (string) ($credential['id'] ?? '');
        if ($id === '' || isset($data[$id])) {
            throw new RuntimeException('Passkey credential is invalid or already registered.');
        }

        $credential['account_id'] = $accountId;
        $data[$id] = $credential;
        $this->write($data);
    }

    /** @return list<array<string, mixed>> */
    public function forAccount(string $accountId): array
    {
        return array_values(array_filter(
            $this->read(),
            static fn (mixed $credential): bool =>
                is_array($credential) && hash_equals((string) ($credential['account_id'] ?? ''), $accountId),
        ));
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        $credential = $this->read()[$id] ?? null;
        return is_array($credential) ? $credential : null;
    }

    public function updateCounter(string $id, int $counter): void
    {
        $data = $this->read();
        if (!isset($data[$id]) || !is_array($data[$id])) {
            throw new RuntimeException('Passkey credential was not found.');
        }
        $data[$id]['sign_count'] = $counter;
        $data[$id]['last_used_at'] = gmdate(DATE_ATOM);
        $this->write($data);
    }

    /** @return array<string, mixed> */
    private function read(): array
    {
        if (!is_file($this->path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($this->path), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Passkey store is invalid.');
        }
        return $decoded;
    }

    /** @param array<string, mixed> $data */
    private function write(array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->files->write($this->path, $json . "\n");
        @chmod($this->path, 0600);
    }
}

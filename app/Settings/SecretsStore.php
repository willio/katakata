<?php

declare(strict_types=1);

namespace Katakata\Settings;

use Katakata\Editorial\AtomicFile;
use RuntimeException;

final class SecretsStore
{
    private const NAME_PATTERN = '/^[a-z][a-z0-9_.]*$/';

    public function __construct(
        private readonly string $path,
        private readonly AtomicFile $files,
        private readonly ?string $appKey,
    ) {
    }

    public function available(): bool
    {
        return $this->appKey !== null && trim($this->appKey) !== '';
    }

    public function has(string $name): bool
    {
        $this->assertAvailable();
        $this->assertValidName($name);

        return array_key_exists($name, $this->readAll());
    }

    public function get(string $name): ?string
    {
        $this->assertAvailable();
        $this->assertValidName($name);

        $encoded = $this->readAll()[$name] ?? null;
        if ($encoded === null) {
            return null;
        }

        return $this->decrypt($name, $encoded);
    }

    public function set(string $name, string $value): void
    {
        $this->assertAvailable();
        $this->assertValidName($name);

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($value, $nonce, $this->key());

        $secrets = $this->readAll();
        $secrets[$name] = base64_encode($nonce . $ciphertext);
        $this->writeAll($secrets);
    }

    public function remove(string $name): void
    {
        $this->assertAvailable();
        $this->assertValidName($name);

        $secrets = $this->readAll();
        if (!array_key_exists($name, $secrets)) {
            return;
        }

        unset($secrets[$name]);
        $this->writeAll($secrets);
    }

    /** @return array<string, string> */
    private function readAll(): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        $contents = file_get_contents($this->path);
        if ($contents === false) {
            throw new RuntimeException('Unable to read the application secret store.');
        }

        try {
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new RuntimeException('The application secret store is invalid JSON.', 0, $error);
        }

        if (!is_array($data)) {
            throw new RuntimeException('The application secret store must contain an object.');
        }

        $secrets = [];
        foreach ($data as $name => $encoded) {
            if (!is_string($name) || !is_string($encoded)) {
                throw new RuntimeException('The application secret store is malformed.');
            }
            $secrets[$name] = $encoded;
        }

        return $secrets;
    }

    /** @param array<string, string> $secrets */
    private function writeAll(array $secrets): void
    {
        $this->files->write(
            $this->path,
            json_encode($secrets, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        );
        @chmod($this->path, 0600);
        @chmod(dirname($this->path), 0700);
    }

    private function decrypt(string $name, string $encoded): string
    {
        $binary = base64_decode($encoded, true);
        if ($binary === false || strlen($binary) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException("Stored secret [{$name}] is corrupted and cannot be decrypted.");
        }

        $nonce = substr($binary, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($binary, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        try {
            $value = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key());
        } catch (\SodiumException $error) {
            throw new RuntimeException("Stored secret [{$name}] is corrupted and cannot be decrypted.", 0, $error);
        }

        if ($value === false) {
            throw new RuntimeException("Stored secret [{$name}] is corrupted and cannot be decrypted.");
        }

        return $value;
    }

    private function key(): string
    {
        return sodium_crypto_generichash((string) $this->appKey, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    private function assertAvailable(): void
    {
        if (!$this->available()) {
            throw new RuntimeException('The application secret store is unavailable: APP_KEY is not configured.');
        }
    }

    private function assertValidName(string $name): void
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new RuntimeException('Secret names must match ' . self::NAME_PATTERN . '.');
        }
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Settings;

use Katakata\Editorial\AtomicFile;
use Katakata\Settings\SecretsStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SecretsStoreTest extends TestCase
{
    private string $root;
    private string $path;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-secrets-' . bin2hex(random_bytes(6));
        $this->path = $this->root . '/secrets.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
        if (is_dir($this->root)) {
            rmdir($this->root);
        }
    }

    public function testItRoundTripsASecret(): void
    {
        $store = $this->store();

        self::assertFalse($store->has('threads.access_token'));
        self::assertNull($store->get('threads.access_token'));

        $store->set('threads.access_token', 'tok-secret-value');

        self::assertTrue($store->has('threads.access_token'));
        self::assertSame('tok-secret-value', $store->get('threads.access_token'));
        self::assertSame('tok-secret-value', $this->store()->get('threads.access_token'));
    }

    public function testItRemovesASecret(): void
    {
        $store = $this->store();
        $store->set('threads.access_token', 'tok-secret-value');

        $store->remove('threads.access_token');

        self::assertFalse($store->has('threads.access_token'));
        self::assertNull($store->get('threads.access_token'));

        $store->remove('threads.access_token');
        self::assertFalse($store->has('threads.access_token'));
    }

    public function testItIsUnavailableWithoutAnAppKey(): void
    {
        $store = new SecretsStore($this->path, new AtomicFile(), null);
        $emptyKey = new SecretsStore($this->path, new AtomicFile(), '');

        self::assertFalse($store->available());
        self::assertFalse($emptyKey->available());
        self::assertTrue($this->store()->available());

        foreach (['has', 'get', 'remove'] as $operation) {
            try {
                $store->{$operation}('threads.access_token');
                self::fail("Operation [{$operation}] must throw when the store is unavailable.");
            } catch (RuntimeException $error) {
                self::assertStringContainsString('unavailable', $error->getMessage());
            }
        }

        $this->expectException(RuntimeException::class);
        $store->set('threads.access_token', 'tok-secret-value');
    }

    public function testItProtectsTheStoreFileAndDirectory(): void
    {
        $this->store()->set('threads.access_token', 'tok-secret-value');

        self::assertSame(0600, fileperms($this->path) & 0777);
        self::assertSame(0700, fileperms($this->root) & 0777);
    }

    public function testItRejectsTamperedCiphertext(): void
    {
        $store = $this->store();
        $store->set('threads.access_token', 'tok-secret-value');

        $secrets = json_decode((string) file_get_contents($this->path), true, flags: JSON_THROW_ON_ERROR);
        $binary = base64_decode($secrets['threads.access_token'], true);
        $binary[strlen($binary) - 1] = $binary[strlen($binary) - 1] === 'a' ? 'b' : 'a';
        $secrets['threads.access_token'] = base64_encode($binary);
        file_put_contents($this->path, json_encode($secrets, JSON_THROW_ON_ERROR));

        $this->expectException(RuntimeException::class);
        $store->get('threads.access_token');
    }

    public function testItRejectsSecretsUnderADifferentAppKey(): void
    {
        $this->store()->set('threads.access_token', 'tok-secret-value');

        $other = new SecretsStore($this->path, new AtomicFile(), 'a-different-app-key');

        $this->expectException(RuntimeException::class);
        $other->get('threads.access_token');
    }

    public function testItValidatesSecretNames(): void
    {
        $store = $this->store();

        foreach (['', '1token', 'Token', 'token name', 'token/name', '-token'] as $name) {
            try {
                $store->set($name, 'value');
                self::fail("Name [{$name}] must be rejected.");
            } catch (RuntimeException $error) {
                self::assertStringContainsString('Secret names must match', $error->getMessage());
            }
        }

        self::assertFileDoesNotExist($this->path);
    }

    public function testItNeverPersistsPlaintextValues(): void
    {
        $store = $this->store();
        $store->set('threads.access_token', 'tok-secret-value');
        $store->set('threads.second_token', 'another-plaintext-secret');

        $contents = (string) file_get_contents($this->path);

        self::assertStringNotContainsString('tok-secret-value', $contents);
        self::assertStringNotContainsString('another-plaintext-secret', $contents);
        self::assertStringContainsString('threads.access_token', $contents);

        $secrets = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(2, $secrets);
        foreach ($secrets as $encoded) {
            $binary = base64_decode($encoded, true);
            self::assertNotFalse($binary);
            self::assertGreaterThan(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, strlen($binary));
        }
    }

    private function store(): SecretsStore
    {
        return new SecretsStore($this->path, new AtomicFile(), 'test-app-key');
    }
}

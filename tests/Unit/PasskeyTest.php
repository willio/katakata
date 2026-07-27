<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use Katakata\Auth\Cbor;
use Katakata\Auth\PasskeyStore;
use Katakata\Auth\WebAuthn;
use Katakata\Editorial\AtomicFile;
use PHPUnit\Framework\TestCase;

final class PasskeyTest extends TestCase
{
    private string $root;
    private PasskeyStore $store;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-passkey-' . bin2hex(random_bytes(6));
        $this->store = new PasskeyStore($this->root . '/passkeys.json', new AtomicFile());
    }

    protected function tearDown(): void
    {
        if (is_file($this->root . '/passkeys.json')) {
            unlink($this->root . '/passkeys.json');
        }
        if (is_dir($this->root)) {
            rmdir($this->root);
        }
    }

    public function testCborDecodesCoseStyleIntegerMap(): void
    {
        $offset = 0;
        $decoded = Cbor::decode(hex2bin('a301022003215820') . str_repeat('x', 32), $offset);

        self::assertSame(2, $decoded[1]);
        self::assertSame(3, $decoded[-1]);
        self::assertSame(str_repeat('x', 32), $decoded[-2]);
    }

    public function testRegistrationOptionsBindAccountAndExcludeExistingCredential(): void
    {
        $this->store->add('account-one', [
            'id' => 'credential-one',
            'public_key' => 'test',
            'algorithm' => -7,
            'sign_count' => 0,
        ]);
        $webauthn = new WebAuthn($this->store, 'https://example.com', 'example.com', 'Katakata');
        $options = $webauthn->registrationOptions([
            'id' => 'account-one',
            'email' => 'owner@example.com',
        ], 'challenge');

        self::assertSame('example.com', $options['rp']['id']);
        self::assertSame(WebAuthn::encode('account-one'), $options['user']['id']);
        self::assertSame('required', $options['authenticatorSelection']['userVerification']);
        self::assertSame('credential-one', $options['excludeCredentials'][0]['id']);
    }

    public function testAuthenticationOptionsOnlyExposeAccountCredentials(): void
    {
        $this->store->add('account-one', [
            'id' => 'credential-one',
            'public_key' => 'test',
            'algorithm' => -7,
            'sign_count' => 0,
        ]);
        $this->store->add('account-two', [
            'id' => 'credential-two',
            'public_key' => 'test',
            'algorithm' => -7,
            'sign_count' => 0,
        ]);
        $webauthn = new WebAuthn($this->store, 'https://example.com', 'example.com', 'Katakata');
        $options = $webauthn->authenticationOptions(['id' => 'account-one'], 'challenge');

        self::assertSame('required', $options['userVerification']);
        self::assertSame([['type' => 'public-key', 'id' => 'credential-one']], $options['allowCredentials']);
    }

    public function testCounterUpdatesPersist(): void
    {
        $this->store->add('account-one', [
            'id' => 'credential-one',
            'public_key' => 'test',
            'algorithm' => -7,
            'sign_count' => 0,
        ]);
        $this->store->updateCounter('credential-one', 8);

        self::assertSame(8, $this->store->find('credential-one')['sign_count']);
        self::assertNotEmpty($this->store->find('credential-one')['last_used_at']);
    }
}

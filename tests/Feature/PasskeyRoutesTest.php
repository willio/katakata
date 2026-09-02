<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use Katakata\Auth\AccountStore;
use Katakata\Auth\PasskeyStore;
use Katakata\Auth\Session;
use Katakata\Auth\WebAuthn;
use Katakata\Editorial\AtomicFile;
use Katakata\Http\Request;
use Katakata\Http\Router;
use OpenSSLAsymmetricKey;
use PHPUnit\Framework\TestCase;

final class PasskeyRoutesTest extends TestCase
{
    private const ORIGIN = 'http://localhost:8000';
    private const RP_ID = 'localhost';

    private string $root;
    private AccountStore $accounts;
    private PasskeyStore $passkeys;
    /** @var array<string, mixed> */
    private array $owner;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-passkey-routes-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0700, true);
        $files = new AtomicFile();
        $this->accounts = new AccountStore($this->root . '/accounts.json', $files);
        $this->passkeys = new PasskeyStore($this->root . '/passkeys.json', $files);
        $this->owner = $this->accounts->createOwner('owner@example.test', 'owner-password-123');
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        @unlink($this->root . '/accounts.json');
        @unlink($this->root . '/passkeys.json');
        @rmdir($this->root);
    }

    public function testPasskeyRoutesAreComposed(): void
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $router = require dirname(__DIR__, 2) . '/bootstrap/routes.php';
        $routes = $router->all();

        foreach (['register/options', 'register', 'login/options', 'login'] as $suffix) {
            self::assertContains(['method' => 'POST', 'path' => '/passkeys/' . $suffix], $routes);
        }
    }

    public function testRegisterOptionsRequiresAuthentication(): void
    {
        $router = $this->router($this->session(null));

        $response = $router->dispatch(new Request('POST', '/passkeys/register/options', body: ['csrf' => 'x']));

        self::assertSame(401, $response->status);
        self::assertStringContainsString('Unauthorised.', $response->body);
    }

    public function testRegisterOptionsRejectsInvalidCsrf(): void
    {
        $router = $this->router($this->session($this->owner));

        $response = $router->dispatch(new Request('POST', '/passkeys/register/options', body: ['csrf' => 'wrong']));

        self::assertSame(419, $response->status);
        self::assertStringContainsString('form expired', $response->body);
    }

    public function testRegisterOptionsBindTheSignedInAccount(): void
    {
        $session = $this->session($this->owner);
        $router = $this->router($session);

        $response = $router->dispatch(new Request('POST', '/passkeys/register/options', body: [
            'csrf' => $session->csrf(),
        ]));

        self::assertSame(200, $response->status, $response->body);
        $options = json_decode($response->body, true);
        self::assertSame(self::RP_ID, $options['rp']['id']);
        self::assertSame(WebAuthn::encode((string) $this->owner['id']), $options['user']['id']);
        self::assertSame($_SESSION['passkey_register']['challenge'], $options['challenge']);
        self::assertSame([], $options['excludeCredentials']);
    }

    public function testRegisterWithoutCeremonyIsRejected(): void
    {
        $session = $this->session($this->owner);
        $router = $this->router($session);

        $response = $router->dispatch(new Request('POST', '/passkeys/register', body: [
            'csrf' => $session->csrf(),
            'credential' => '{}',
        ]));

        self::assertSame(422, $response->status);
        self::assertStringContainsString('ceremony expired', $response->body);
    }

    public function testRegisterRejectsMalformedCredentialPayload(): void
    {
        $session = $this->session($this->owner);
        $router = $this->router($session);
        $router->dispatch(new Request('POST', '/passkeys/register/options', body: ['csrf' => $session->csrf()]));

        $response = $router->dispatch(new Request('POST', '/passkeys/register', body: [
            'csrf' => $session->csrf(),
            'credential' => 'not-json',
        ]));

        self::assertSame(422, $response->status);
        self::assertStringContainsString('passkey response is invalid', $response->body);
    }

    public function testRegisterRejectsMismatchedChallenge(): void
    {
        $session = $this->session($this->owner);
        $router = $this->router($session);
        $router->dispatch(new Request('POST', '/passkeys/register/options', body: ['csrf' => $session->csrf()]));

        $response = $router->dispatch(new Request('POST', '/passkeys/register', body: [
            'csrf' => $session->csrf(),
            'credential' => json_encode([
                'id' => 'credential-one',
                'clientDataJSON' => WebAuthn::encode((string) json_encode([
                    'type' => 'webauthn.create',
                    'challenge' => 'a-different-challenge',
                    'origin' => self::ORIGIN,
                    'crossOrigin' => false,
                ])),
                'attestationObject' => WebAuthn::encode('unused'),
            ]),
        ]));

        self::assertSame(422, $response->status);
        self::assertStringContainsString('client data validation failed', $response->body);
        self::assertSame([], $this->passkeys->forAccount((string) $this->owner['id']));
    }

    public function testRegisterStoresACredentialForTheSignedInAccount(): void
    {
        $session = $this->session($this->owner);
        $router = $this->router($session);
        $router->dispatch(new Request('POST', '/passkeys/register/options', body: ['csrf' => $session->csrf()]));
        $challenge = (string) $_SESSION['passkey_register']['challenge'];

        $key = $this->generateKey();
        $credentialId = random_bytes(16);
        $attestation = self::cbor([
            'fmt' => 'none',
            'authData' => $this->attestationAuthData($credentialId, $key),
        ]);
        $clientJson = (string) json_encode([
            'type' => 'webauthn.create',
            'challenge' => $challenge,
            'origin' => self::ORIGIN,
            'crossOrigin' => false,
        ]);

        $response = $router->dispatch(new Request('POST', '/passkeys/register', body: [
            'csrf' => $session->csrf(),
            'credential' => json_encode([
                'id' => WebAuthn::encode($credentialId),
                'clientDataJSON' => WebAuthn::encode($clientJson),
                'attestationObject' => WebAuthn::encode($attestation),
            ]),
        ]));

        self::assertSame(200, $response->status, $response->body);
        $stored = $this->passkeys->find(WebAuthn::encode($credentialId));
        self::assertNotNull($stored);
        self::assertSame((string) $this->owner['id'], $stored['account_id']);
        self::assertSame(-7, $stored['algorithm']);
    }

    public function testLoginOptionsRejectsInvalidCsrf(): void
    {
        $router = $this->router($this->session(null));

        $response = $router->dispatch(new Request('POST', '/passkeys/login/options', body: [
            'csrf' => 'wrong',
            'email' => 'owner@example.test',
        ]));

        self::assertSame(419, $response->status);
    }

    public function testLoginOptionsUseTheSameGenericErrorForUnknownEmailAndMissingPasskey(): void
    {
        $session = $this->session(null);
        $router = $this->router($session);

        $unknown = $router->dispatch(new Request('POST', '/passkeys/login/options', body: [
            'csrf' => $session->csrf(),
            'email' => 'nobody@example.test',
        ]));
        $withoutPasskey = $router->dispatch(new Request('POST', '/passkeys/login/options', body: [
            'csrf' => $session->csrf(),
            'email' => 'owner@example.test',
        ]));

        self::assertSame(422, $unknown->status);
        self::assertSame(422, $withoutPasskey->status);
        self::assertSame(
            json_decode($unknown->body, true)['error'],
            json_decode($withoutPasskey->body, true)['error'],
        );
    }

    public function testLoginOptionsExposeOnlyTheAccountCredentials(): void
    {
        $this->passkeys->add((string) $this->owner['id'], [
            'id' => 'credential-one',
            'public_key' => 'test',
            'algorithm' => -7,
            'sign_count' => 0,
        ]);
        $session = $this->session(null);
        $router = $this->router($session);

        $response = $router->dispatch(new Request('POST', '/passkeys/login/options', body: [
            'csrf' => $session->csrf(),
            'email' => 'owner@example.test',
        ]));

        self::assertSame(200, $response->status, $response->body);
        $options = json_decode($response->body, true);
        self::assertSame([['type' => 'public-key', 'id' => 'credential-one']], $options['allowCredentials']);
        self::assertSame($_SESSION['passkey_login']['challenge'], $options['challenge']);
    }

    public function testLoginWithoutCeremonyIsRejected(): void
    {
        $session = $this->session(null);
        $router = $this->router($session);

        $response = $router->dispatch(new Request('POST', '/passkeys/login', body: [
            'csrf' => $session->csrf(),
            'credential' => '{}',
        ]));

        self::assertSame(422, $response->status);
        self::assertStringContainsString('ceremony expired', $response->body);
    }

    public function testLoginRejectsAnUnknownCredential(): void
    {
        $this->passkeys->add((string) $this->owner['id'], [
            'id' => 'credential-one',
            'public_key' => 'test',
            'algorithm' => -7,
            'sign_count' => 0,
        ]);
        $session = $this->session(null);
        $router = $this->router($session);
        $router->dispatch(new Request('POST', '/passkeys/login/options', body: [
            'csrf' => $session->csrf(),
            'email' => 'owner@example.test',
        ]));

        $response = $router->dispatch(new Request('POST', '/passkeys/login', body: [
            'csrf' => $session->csrf(),
            'credential' => json_encode([
                'id' => 'credential-unknown',
                'clientDataJSON' => WebAuthn::encode('{}'),
                'authenticatorData' => WebAuthn::encode(''),
                'signature' => WebAuthn::encode(''),
                'userHandle' => '',
            ]),
        ]));

        self::assertSame(422, $response->status);
        self::assertStringContainsString('not valid for this account', $response->body);
        self::assertNull($session->user());
    }

    public function testLoginVerifiesTheSignatureAndSignsIn(): void
    {
        $key = $this->generateKey();
        $credentialId = random_bytes(16);
        $this->passkeys->add((string) $this->owner['id'], [
            'id' => WebAuthn::encode($credentialId),
            'public_key' => openssl_pkey_get_details($key)['key'],
            'algorithm' => -7,
            'sign_count' => 0,
        ]);
        $session = $this->session(null);
        $router = $this->router($session);
        $router->dispatch(new Request('POST', '/passkeys/login/options', body: [
            'csrf' => $session->csrf(),
            'email' => 'owner@example.test',
        ]));
        $challenge = (string) $_SESSION['passkey_login']['challenge'];

        $clientJson = (string) json_encode([
            'type' => 'webauthn.get',
            'challenge' => $challenge,
            'origin' => self::ORIGIN,
            'crossOrigin' => false,
        ]);
        $authData = hash('sha256', self::RP_ID, true) . chr(0x05) . pack('N', 1);
        openssl_sign($authData . hash('sha256', $clientJson, true), $signature, $key, OPENSSL_ALGO_SHA256);

        $response = $router->dispatch(new Request('POST', '/passkeys/login', body: [
            'csrf' => $session->csrf(),
            'credential' => json_encode([
                'id' => WebAuthn::encode($credentialId),
                'clientDataJSON' => WebAuthn::encode($clientJson),
                'authenticatorData' => WebAuthn::encode($authData),
                'signature' => WebAuthn::encode($signature),
                'userHandle' => '',
            ]),
        ]));

        self::assertSame(200, $response->status, $response->body);
        $result = json_decode($response->body, true);
        self::assertTrue($result['ok']);
        self::assertSame('/dashboard', $result['redirect']);
        self::assertSame((string) $this->owner['id'], $_SESSION['account_id']);
        self::assertSame(1, $this->passkeys->find(WebAuthn::encode($credentialId))['sign_count']);
    }

    public function testLoginRejectsAnInvalidSignature(): void
    {
        $key = $this->generateKey();
        $credentialId = random_bytes(16);
        $this->passkeys->add((string) $this->owner['id'], [
            'id' => WebAuthn::encode($credentialId),
            'public_key' => openssl_pkey_get_details($key)['key'],
            'algorithm' => -7,
            'sign_count' => 0,
        ]);
        $session = $this->session(null);
        $router = $this->router($session);
        $router->dispatch(new Request('POST', '/passkeys/login/options', body: [
            'csrf' => $session->csrf(),
            'email' => 'owner@example.test',
        ]));
        $challenge = (string) $_SESSION['passkey_login']['challenge'];

        $clientJson = (string) json_encode([
            'type' => 'webauthn.get',
            'challenge' => $challenge,
            'origin' => self::ORIGIN,
            'crossOrigin' => false,
        ]);
        $authData = hash('sha256', self::RP_ID, true) . chr(0x05) . pack('N', 1);
        openssl_sign('a different message', $signature, $key, OPENSSL_ALGO_SHA256);

        $response = $router->dispatch(new Request('POST', '/passkeys/login', body: [
            'csrf' => $session->csrf(),
            'credential' => json_encode([
                'id' => WebAuthn::encode($credentialId),
                'clientDataJSON' => WebAuthn::encode($clientJson),
                'authenticatorData' => WebAuthn::encode($authData),
                'signature' => WebAuthn::encode($signature),
                'userHandle' => '',
            ]),
        ]));

        self::assertSame(422, $response->status);
        self::assertStringContainsString('signature verification failed', $response->body);
        self::assertNull($session->user());
    }

    public function testSettingsPageRendersPasskeyManagement(): void
    {
        $this->passkeys->add((string) $this->owner['id'], [
            'id' => 'credential-one',
            'public_key' => 'test',
            'algorithm' => -7,
            'sign_count' => 0,
            'created_at' => '2026-08-01T00:00:00+00:00',
        ]);
        $session = $this->session($this->owner);
        $router = $this->router($session);

        $response = $router->dispatch(new Request('GET', '/dashboard/settings'));

        self::assertSame(200, $response->status, $response->body);
        self::assertStringContainsString('<a href="#passkeys">Passkeys</a>', $response->body);
        self::assertStringContainsString('data-passkey-register', $response->body);
        self::assertStringContainsString('data-passkey-status', $response->body);
        self::assertStringContainsString('/assets/js/passkeys.js', $response->body);
        self::assertStringContainsString('2026-08-01T00:00:00+00:00', $response->body);
    }

    private function router(Session $session): Router
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $app->instance(Session::class, $session);
        $app->instance(AccountStore::class, $this->accounts);
        $app->instance(PasskeyStore::class, $this->passkeys);
        $app->instance(WebAuthn::class, new WebAuthn($this->passkeys, self::ORIGIN, self::RP_ID, 'Katakata'));

        return require dirname(__DIR__, 2) . '/bootstrap/routes.php';
    }

    /** @param array<string, mixed>|null $account */
    private function session(?array $account): Session
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        session_id('passkey-routes-' . bin2hex(random_bytes(8)));

        $session = new Session($this->accounts);
        if ($account === null) {
            $session->start();
        } else {
            $session->login($account);
        }

        return $session;
    }

    private function generateKey(): OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        self::assertInstanceOf(OpenSSLAsymmetricKey::class, $key);

        return $key;
    }

    private function attestationAuthData(string $credentialId, OpenSSLAsymmetricKey $key): string
    {
        $details = openssl_pkey_get_details($key);
        $coseKey = self::cbor([
            1 => 2,
            3 => -7,
            -1 => 1,
            -2 => $details['ec']['x'],
            -3 => $details['ec']['y'],
        ]);

        return hash('sha256', self::RP_ID, true)
            . chr(0x45)
            . pack('N', 1)
            . str_repeat("\0", 16)
            . pack('n', strlen($credentialId))
            . $credentialId
            . $coseKey;
    }

    /** @param mixed $value */
    private static function cbor(mixed $value): string
    {
        if (is_int($value)) {
            return $value >= 0 ? self::cborHead(0, $value) : self::cborHead(1, -1 - $value);
        }
        if (is_string($value)) {
            return self::cborHead(2, strlen($value)) . $value;
        }
        if (is_array($value)) {
            $list = array_is_list($value);
            $encoded = self::cborHead($list ? 4 : 5, count($value));
            foreach ($value as $key => $item) {
                $encoded .= ($list ? '' : self::cbor($key)) . self::cbor($item);
            }

            return $encoded;
        }

        throw new \RuntimeException('Unsupported test CBOR value.');
    }

    private static function cborHead(int $major, int $value): string
    {
        if ($value < 24) {
            return chr($major << 5 | $value);
        }
        if ($value < 256) {
            return chr($major << 5 | 24) . chr($value);
        }
        if ($value < 65536) {
            return chr($major << 5 | 25) . pack('n', $value);
        }

        return chr($major << 5 | 26) . pack('N', $value);
    }
}

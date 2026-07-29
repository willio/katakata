<?php

declare(strict_types=1);

namespace Katakata\Auth;

use RuntimeException;

final class WebAuthn
{
    public function __construct(
        private readonly PasskeyStore $passkeys,
        private readonly string $origin,
        private readonly string $rpId,
        private readonly string $rpName,
    ) {
    }

    /** @param array<string, mixed> $account
     *  @return array<string, mixed>
     */
    public function registrationOptions(array $account, string $challenge): array
    {
        return [
            'challenge' => $challenge,
            'rp' => ['id' => $this->rpId, 'name' => $this->rpName],
            'user' => [
                'id' => self::encode((string) $account['id']),
                'name' => (string) $account['email'],
                'displayName' => (string) $account['email'],
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],
                ['type' => 'public-key', 'alg' => -257],
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'authenticatorSelection' => [
                'residentKey' => 'preferred',
                'userVerification' => 'required',
            ],
            'excludeCredentials' => array_map(
                static fn (array $item): array => ['type' => 'public-key', 'id' => $item['id']],
                $this->passkeys->forAccount((string) $account['id']),
            ),
        ];
    }

    /** @param array<string, mixed> $account
     *  @return array<string, mixed>
     */
    public function authenticationOptions(array $account, string $challenge): array
    {
        $credentials = $this->passkeys->forAccount((string) $account['id']);
        if ($credentials === []) {
            throw new RuntimeException('No passkey is registered for this account.');
        }

        return [
            'challenge' => $challenge,
            'rpId' => $this->rpId,
            'timeout' => 60000,
            'userVerification' => 'required',
            'allowCredentials' => array_map(
                static fn (array $item): array => ['type' => 'public-key', 'id' => $item['id']],
                $credentials,
            ),
        ];
    }

    /** @param array<string, string> $response */
    public function register(string $accountId, string $expectedChallenge, array $response): void
    {
        $clientJson = self::decode($response['clientDataJSON'] ?? '');
        $this->validateClient($clientJson, 'webauthn.create', $expectedChallenge);
        $attestation = self::decode($response['attestationObject'] ?? '');
        $offset = 0;
        $object = Cbor::decode($attestation, $offset);

        if (!is_array($object) || ($object['fmt'] ?? null) !== 'none' || !is_string($object['authData'] ?? null)) {
            throw new RuntimeException('Unsupported passkey attestation.');
        }

        $auth = $this->authenticatorData($object['authData'], true);
        $credentialId = self::encode($auth['credential_id']);
        if (!hash_equals($credentialId, $response['id'] ?? '')) {
            throw new RuntimeException('Passkey credential ID does not match.');
        }

        $this->passkeys->add($accountId, [
            'id' => $credentialId,
            'public_key' => $this->publicKey($auth['cose_key']),
            'algorithm' => (int) ($auth['cose_key'][3] ?? 0),
            'sign_count' => $auth['sign_count'],
            'created_at' => gmdate(DATE_ATOM),
        ]);
    }

    /** @param array<string, string> $response */
    public function authenticate(string $accountId, string $expectedChallenge, array $response): void
    {
        $credential = $this->passkeys->find($response['id'] ?? '');
        if ($credential === null || !hash_equals((string) $credential['account_id'], $accountId)) {
            throw new RuntimeException('Passkey credential is not valid for this account.');
        }

        $clientJson = self::decode($response['clientDataJSON'] ?? '');
        $this->validateClient($clientJson, 'webauthn.get', $expectedChallenge);
        $authData = self::decode($response['authenticatorData'] ?? '');
        $auth = $this->authenticatorData($authData, false);
        $signature = self::decode($response['signature'] ?? '');
        $signed = $authData . hash('sha256', $clientJson, true);
        $algorithm = (int) ($credential['algorithm'] ?? 0);
        $opensslAlgorithm = match ($algorithm) {
            -7, -257 => OPENSSL_ALGO_SHA256,
            default => throw new RuntimeException('Unsupported passkey signature algorithm.'),
        };

        if (openssl_verify($signed, $signature, (string) $credential['public_key'], $opensslAlgorithm) !== 1) {
            throw new RuntimeException('Passkey signature verification failed.');
        }

        $stored = (int) ($credential['sign_count'] ?? 0);
        if ($auth['sign_count'] !== 0 && $auth['sign_count'] <= $stored) {
            throw new RuntimeException('Passkey signature counter did not advance.');
        }
        $this->passkeys->updateCounter((string) $credential['id'], $auth['sign_count']);
    }

    /** @return array<string, mixed> */
    private function validateClient(string $json, string $type, string $challenge): array
    {
        $client = json_decode($json, true);
        if (
            !is_array($client)
            || !hash_equals($type, (string) ($client['type'] ?? ''))
            || !hash_equals($challenge, (string) ($client['challenge'] ?? ''))
            || !hash_equals($this->origin, (string) ($client['origin'] ?? ''))
            || ($client['crossOrigin'] ?? false) !== false
        ) {
            throw new RuntimeException('Passkey client data validation failed.');
        }
        return $client;
    }

    /** @return array{sign_count: int, credential_id: string, cose_key: array<int|string, mixed>} */
    private function authenticatorData(string $data, bool $attested): array
    {
        if (strlen($data) < 37 || !hash_equals(hash('sha256', $this->rpId, true), substr($data, 0, 32))) {
            throw new RuntimeException('Passkey relying-party validation failed.');
        }
        $flags = ord($data[32]);
        if (($flags & 0x01) === 0 || ($flags & 0x04) === 0) {
            throw new RuntimeException('Passkey user verification is required.');
        }
        $counter = unpack('N', substr($data, 33, 4))[1];
        if (!$attested) {
            return ['sign_count' => $counter, 'credential_id' => '', 'cose_key' => []];
        }
        if (($flags & 0x40) === 0 || strlen($data) < 55) {
            throw new RuntimeException('Passkey attested credential data is missing.');
        }
        $length = unpack('n', substr($data, 53, 2))[1];
        $credentialId = substr($data, 55, $length);
        if (strlen($credentialId) !== $length) {
            throw new RuntimeException('Passkey credential data is truncated.');
        }
        $offset = 55 + $length;
        $cose = Cbor::decode($data, $offset);
        if (!is_array($cose)) {
            throw new RuntimeException('Passkey public key is invalid.');
        }
        return ['sign_count' => $counter, 'credential_id' => $credentialId, 'cose_key' => $cose];
    }

    /** @param array<int|string, mixed> $key */
    private function publicKey(array $key): string
    {
        $algorithm = (int) ($key[3] ?? 0);
        if ($algorithm === -7 && ($key[1] ?? null) === 2 && ($key[-1] ?? null) === 1) {
            $point = "\x04" . (string) ($key[-2] ?? '') . (string) ($key[-3] ?? '');
            if (strlen($point) !== 65) {
                throw new RuntimeException('Invalid P-256 passkey public key.');
            }
            $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $point;
            return self::pem($der);
        }
        if ($algorithm === -257 && ($key[1] ?? null) === 3) {
            $rsa = self::derInteger((string) ($key[-1] ?? '')) . self::derInteger((string) ($key[-2] ?? ''));
            $rsa = self::der(0x30, $rsa);
            $algorithmId = hex2bin('300d06092a864886f70d0101010500');
            return self::pem(self::der(0x30, $algorithmId . self::der(0x03, "\x00" . $rsa)));
        }
        throw new RuntimeException('Unsupported passkey public key.');
    }

    private static function derInteger(string $value): string
    {
        $value = ltrim($value, "\x00");
        if ($value === '') {
            $value = "\x00";
        } elseif ((ord($value[0]) & 0x80) !== 0) {
            $value = "\x00" . $value;
        }
        return self::der(0x02, $value);
    }

    private static function der(int $tag, string $value): string
    {
        $length = strlen($value);
        if ($length < 128) {
            return chr($tag) . chr($length) . $value;
        }
        $encoded = ltrim(pack('N', $length), "\x00");
        return chr($tag) . chr(0x80 | strlen($encoded)) . $encoded . $value;
    }

    private static function pem(string $der): string
    {
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    public static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public static function decode(string $value): string
    {
        if ($value === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
            throw new RuntimeException('Invalid base64url value.');
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid base64url value.');
        }
        return $decoded;
    }
}

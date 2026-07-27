<?php

declare(strict_types=1);

namespace Katakata\Auth;

final class Session
{
    private bool $started = false;

    public function __construct(private readonly AccountStore $accounts)
    {
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }

        session_name('katakata_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        $this->started = true;
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        $this->start();
        $id = $_SESSION['account_id'] ?? null;

        return is_string($id) ? $this->accounts->find($id) : null;
    }

    /** @param array<string, mixed> $account */
    public function login(array $account): void
    {
        $this->start();
        session_regenerate_id(true);
        $_SESSION = [
            'account_id' => (string) $account['id'],
            'authenticated_at' => time(),
            'csrf' => bin2hex(random_bytes(32)),
        ];
    }

    public function logout(): void
    {
        $this->start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        $this->started = false;
    }

    public function csrf(): string
    {
        $this->start();
        if (!is_string($_SESSION['csrf'] ?? null)) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf'];
    }

    public function validCsrf(?string $token): bool
    {
        return is_string($token) && hash_equals($this->csrf(), $token);
    }

    /** @param array<string, mixed> $data */
    public function beginPasskey(string $ceremony, array $data): string
    {
        $this->start();
        $challenge = WebAuthn::encode(random_bytes(32));
        $_SESSION['passkey_' . $ceremony] = $data + [
            'challenge' => $challenge,
            'expires_at' => time() + 300,
        ];
        return $challenge;
    }

    /** @return array<string, mixed>|null */
    public function consumePasskey(string $ceremony): ?array
    {
        $this->start();
        $key = 'passkey_' . $ceremony;
        $data = $_SESSION[$key] ?? null;
        unset($_SESSION[$key]);

        return is_array($data) && (int) ($data['expires_at'] ?? 0) >= time() ? $data : null;
    }

    public function canInvite(): bool
    {
        return in_array($this->user()['role'] ?? null, ['owner', 'admin'], true);
    }
}

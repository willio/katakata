<?php

declare(strict_types=1);

namespace Katakata\Auth;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use Katakata\Editorial\AtomicFile;
use RuntimeException;

final class AccountStore
{
    public function __construct(
        private readonly string $path,
        private readonly AtomicFile $files,
    ) {
    }

    public function hasAccounts(): bool
    {
        return $this->read()['accounts'] !== [];
    }

    /** @return array<string, mixed> */
    public function createOwner(string $email, string $password): array
    {
        if ($this->hasAccounts()) {
            throw new RuntimeException('The owner account already exists.');
        }

        return $this->createAccount($email, $password, 'owner');
    }

    /** @return array{token: string, expires_at: string} */
    public function invite(string $email, string $role = 'editor', ?DateTimeImmutable $now = null): array
    {
        $email = $this->email($email);
        if (!in_array($role, ['admin', 'editor'], true)) {
            throw new InvalidArgumentException('Invitation role must be admin or editor.');
        }

        $now ??= new DateTimeImmutable();
        $token = bin2hex(random_bytes(32));
        $data = $this->read();
        $data['invitations'][hash('sha256', $token)] = [
            'email' => $email,
            'role' => $role,
            'expires_at' => $now->add(new DateInterval('P2D'))->format(DateTimeImmutable::ATOM),
        ];
        $this->write($data);

        return ['token' => $token, 'expires_at' => $data['invitations'][hash('sha256', $token)]['expires_at']];
    }

    /** @return array<string, mixed> */
    public function accept(string $token, string $email, string $password, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();
        $data = $this->read();
        $key = hash('sha256', $token);
        $invite = $data['invitations'][$key] ?? null;

        if (!is_array($invite) || new DateTimeImmutable((string) $invite['expires_at']) < $now) {
            throw new RuntimeException('Invitation is invalid or expired.');
        }

        if (!hash_equals((string) $invite['email'], $this->email($email))) {
            throw new RuntimeException('Invitation email does not match.');
        }

        unset($data['invitations'][$key]);
        $account = $this->account((string) $invite['email'], $password, (string) $invite['role']);
        $data['accounts'][$account['id']] = $account;
        $this->write($data);

        return $account;
    }

    /** @return array<string, mixed>|null */
    public function authenticate(string $email, string $password): ?array
    {
        $email = strtolower(trim($email));
        foreach ($this->read()['accounts'] as $account) {
            if (
                is_array($account)
                && hash_equals((string) ($account['email'] ?? ''), $email)
                && password_verify($password, (string) ($account['password_hash'] ?? ''))
            ) {
                if (password_needs_rehash((string) $account['password_hash'], PASSWORD_DEFAULT)) {
                    $this->rehash((string) $account['id'], $password);
                }

                unset($account['password_hash']);
                return $account;
            }
        }

        password_verify($password, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        return null;
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        $account = $this->read()['accounts'][$id] ?? null;
        if (!is_array($account)) {
            return null;
        }

        unset($account['password_hash']);
        return $account;
    }

    /** @return array<string, mixed> */
    private function createAccount(string $email, string $password, string $role): array
    {
        $data = $this->read();
        $account = $this->account($email, $password, $role);
        $data['accounts'][$account['id']] = $account;
        $this->write($data);

        return $account;
    }

    /** @return array<string, mixed> */
    private function account(string $email, string $password, string $role): array
    {
        $email = $this->email($email);
        if (strlen($password) < 12) {
            throw new InvalidArgumentException('Password must contain at least 12 characters.');
        }

        return [
            'id' => bin2hex(random_bytes(16)),
            'email' => $email,
            'role' => $role,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
        ];
    }

    private function rehash(string $id, string $password): void
    {
        $data = $this->read();
        if (isset($data['accounts'][$id])) {
            $data['accounts'][$id]['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $this->write($data);
        }
    }

    private function email(string $email): string
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid email address is required.');
        }

        return $email;
    }

    /** @return array{accounts: array<string, mixed>, invitations: array<string, mixed>} */
    private function read(): array
    {
        if (!is_file($this->path)) {
            return ['accounts' => [], 'invitations' => []];
        }

        $decoded = json_decode((string) file_get_contents($this->path), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Authentication store is invalid.');
        }

        return [
            'accounts' => is_array($decoded['accounts'] ?? null) ? $decoded['accounts'] : [],
            'invitations' => is_array($decoded['invitations'] ?? null) ? $decoded['invitations'] : [],
        ];
    }

    /** @param array<string, mixed> $data */
    private function write(array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->files->write($this->path, $json . "\n");
        @chmod($this->path, 0600);
    }
}

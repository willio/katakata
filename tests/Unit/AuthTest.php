<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use DateTimeImmutable;
use Katakata\Auth\AccountStore;
use Katakata\Editorial\AtomicFile;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AuthTest extends TestCase
{
    private string $root;
    private AccountStore $accounts;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-auth-' . bin2hex(random_bytes(6));
        $this->accounts = new AccountStore($this->root . '/accounts.json', new AtomicFile());
    }

    protected function tearDown(): void
    {
        if (is_file($this->root . '/accounts.json')) {
            unlink($this->root . '/accounts.json');
        }
        if (is_dir($this->root)) {
            rmdir($this->root);
        }
    }

    public function testOwnerCanOnlyBeBootstrappedOnce(): void
    {
        $owner = $this->accounts->createOwner('owner@example.com', 'a-secure-password');
        self::assertSame('owner', $owner['role']);
        self::assertSame('owner@example.com', $this->accounts->authenticate('owner@example.com', 'a-secure-password')['email']);

        $this->expectException(RuntimeException::class);
        $this->accounts->createOwner('other@example.com', 'another-password');
    }

    public function testInvitationIsSingleUseAndBoundToItsEmail(): void
    {
        $invite = $this->accounts->invite('editor@example.com', 'editor', new DateTimeImmutable('2026-07-28T00:00:00Z'));
        $account = $this->accounts->accept(
            $invite['token'],
            'editor@example.com',
            'a-secure-password',
            new DateTimeImmutable('2026-07-28T01:00:00Z'),
        );
        self::assertSame('editor', $account['role']);

        $this->expectException(RuntimeException::class);
        $this->accounts->accept(
            $invite['token'],
            'editor@example.com',
            'another-password',
            new DateTimeImmutable('2026-07-28T02:00:00Z'),
        );
    }

    public function testExpiredInvitationIsRejected(): void
    {
        $invite = $this->accounts->invite('editor@example.com', 'editor', new DateTimeImmutable('2026-07-20T00:00:00Z'));

        $this->expectException(RuntimeException::class);
        $this->accounts->accept(
            $invite['token'],
            'editor@example.com',
            'a-secure-password',
            new DateTimeImmutable('2026-07-23T00:00:01Z'),
        );
    }
}

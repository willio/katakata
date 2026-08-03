<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class SettingsMailboxOwnerRenameContractTest extends TestCase
{
    public function testOwnerRenamePreservesPrivateMailboxConfiguration(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/settings-mailboxes.php');
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/dashboard-settings-mailboxes.php');

        self::assertIsString($routes);
        self::assertIsString($view);
        self::assertStringContainsString('$existing = $store->find($id);', $routes);
        self::assertStringContainsString('host: $existing->host', $routes);
        self::assertStringContainsString('port: $existing->port', $routes);
        self::assertStringContainsString('mailbox: $existing->mailbox', $routes);
        self::assertStringContainsString('usernameSecret: $existing->usernameSecret', $routes);
        self::assertStringContainsString('passwordSecret: $existing->passwordSecret', $routes);
        self::assertStringContainsString('enabled: $existing->enabled', $routes);
        self::assertStringNotContainsString('name="host"', $view);
        self::assertStringNotContainsString('name="port"', $view);
        self::assertStringNotContainsString('name="mailbox"', $view);
        self::assertStringNotContainsString('name="username_secret"', $view);
        self::assertStringNotContainsString('name="password_secret"', $view);
    }
}

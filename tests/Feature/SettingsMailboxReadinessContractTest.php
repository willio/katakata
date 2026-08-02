<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class SettingsMailboxReadinessContractTest extends TestCase
{
    public function testSettingsDeriveMailboxReadinessFromAccountRegistryCredentialsAndCaches(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/settings.php');

        self::assertIsString($routes);
        self::assertStringContainsString('MailboxAccountStore::class)->all()', $routes);
        self::assertStringContainsString('MailboxCredentialResolver::class', $routes);
        self::assertStringContainsString('Mailbox::class)->readiness()', $routes);
        self::assertStringNotContainsString("'username' =>", $routes);
        self::assertStringNotContainsString("'password' =>", $routes);
    }

    public function testNormalSettingsTranslateMailboxStateIntoProductLanguage(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/dashboard-settings.php');

        self::assertIsString($view);
        foreach (['Available', 'Waiting for setup', 'Needs attention', 'Paused'] as $label) {
            self::assertStringContainsString($label, $view);
        }
        self::assertStringContainsString('Manage reader inboxes', $view);
        self::assertStringNotContainsString('account_id', $view);
        self::assertStringNotContainsString('last_synced_at', $view);
        self::assertStringNotContainsString('usernameSecret', $view);
        self::assertStringNotContainsString('passwordSecret', $view);
        self::assertStringNotContainsString('sync-mail.php', $view);
    }

    public function testMailboxManagementKeepsDeploymentTopologyOutOfOwnerView(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/dashboard-settings-mailboxes.php');

        self::assertIsString($view);
        self::assertStringContainsString('Reader inboxes', $view);
        self::assertStringContainsString('Inbox name', $view);
        self::assertStringContainsString('Pause inbox', $view);
        self::assertStringContainsString('Resume inbox', $view);
        self::assertStringContainsString('Import profile', $view);
        self::assertStringNotContainsString('Username variable name', $view);
        self::assertStringNotContainsString('Password variable name', $view);
        self::assertStringNotContainsString('sync-mail.php', $view);
        self::assertStringNotContainsString('--account=', $view);
        self::assertStringNotContainsString('Last sync', $view);
        self::assertStringNotContainsString('Cache path', $view);
        self::assertStringNotContainsString('name="host"', $view);
        self::assertStringNotContainsString('name="port"', $view);
        self::assertStringNotContainsString('name="mailbox"', $view);
        self::assertStringNotContainsString('name="username_secret"', $view);
        self::assertStringNotContainsString('name="password_secret"', $view);
    }

    public function testUnavailablePlaceholderSectionsAreNotRendered(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/dashboard-settings.php');

        self::assertIsString($view);
        self::assertStringNotContainsString('>Appearance<', $view);
        self::assertStringNotContainsString('>Account &amp; Security<', $view);
        self::assertStringNotContainsString('>System<', $view);
    }
}

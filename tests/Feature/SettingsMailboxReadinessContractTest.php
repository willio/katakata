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
        self::assertStringContainsString("(array) (\$mailbox['accounts'] ?? [])", $routes);
        self::assertStringContainsString("'account_id' => \$account->id", $routes);
        self::assertStringContainsString("'label' => \$account->label", $routes);
        self::assertStringContainsString("'configured' => \$missing === []", $routes);
        self::assertStringContainsString("'missing' => \$missing", $routes);
        self::assertStringContainsString("'enabled' => \$account->enabled", $routes);
        self::assertStringContainsString("'last_synced_at' => \$state['last_synced_at'] ?? null", $routes);
        self::assertStringNotContainsString("'username' =>", $routes);
        self::assertStringNotContainsString("'password' =>", $routes);
    }

    public function testMailboxReadinessDistinguishesAggregateMultiAccountStates(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/settings.php');

        self::assertIsString($routes);
        self::assertStringContainsString("match ((string) (\$mailbox['status'] ?? 'disabled'))", $routes);
        self::assertStringContainsString("'ready' => ['status' => 'Ready'", $routes);
        self::assertStringContainsString("'partial' => ['status' => 'Partially available'", $routes);
        self::assertStringContainsString("'needs_setup' => ['status' => 'Needs setup'", $routes);
        self::assertStringContainsString("default => ['status' => 'Disabled'", $routes);
        self::assertStringContainsString("'mailbox' => \$mailboxState + ['accounts' => \$accountStates]", $routes);
        self::assertStringContainsString('Healthy mailbox caches remain available', $routes);
        self::assertStringContainsString('No mailbox account is enabled', $routes);
    }

    public function testSettingsExposeAccountManagementWithoutCredentialValues(): void
    {
        $settingsView = file_get_contents(dirname(__DIR__, 2) . '/resources/views/dashboard-settings.php');
        $accountsView = file_get_contents(dirname(__DIR__, 2) . '/resources/views/dashboard-settings-mailboxes.php');

        self::assertIsString($settingsView);
        self::assertIsString($accountsView);
        self::assertStringContainsString('href="#mailbox"', $settingsView);
        self::assertStringContainsString('id="mailbox"', $settingsView);
        self::assertStringContainsString('/dashboard/settings/mailboxes', $accountsView);
        self::assertStringContainsString('Username variable name', $accountsView);
        self::assertStringContainsString('Password variable name', $accountsView);
        self::assertStringContainsString('php private/jobs/sync-mail.php', $accountsView);
        self::assertStringContainsString('--account=&lt;id&gt;', $accountsView);
        self::assertStringNotContainsString("\$account->username", $accountsView);
        self::assertStringNotContainsString("\$account->password", $accountsView);
    }
}

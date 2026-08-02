<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class SettingsMailboxReadinessContractTest extends TestCase
{
    public function testSettingsDeriveMailboxReadinessFromDeploymentAndPrivateCacheState(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/settings.php');

        self::assertIsString($routes);
        self::assertStringContainsString('ImapSettings::class', $routes);
        self::assertStringContainsString('Mailbox::class)->readiness()', $routes);
        self::assertStringContainsString("'mailbox' => \$mailboxState", $routes);
        self::assertStringContainsString("'configured' => \$imap->configured()", $routes);
        self::assertStringContainsString("'missing' => \$imap->missing()", $routes);
        self::assertStringContainsString('private/jobs/sync-mail.php', $routes);
        self::assertStringNotContainsString("'username' =>", $routes);
        self::assertStringNotContainsString("'password' =>", $routes);
    }

    public function testMailboxReadinessDistinguishesReadyErrorAndNeedsSetup(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/settings.php');

        self::assertIsString($routes);
        self::assertStringContainsString("'ready' =>", $routes);
        self::assertStringContainsString("'error' =>", $routes);
        self::assertStringContainsString("'status' => 'Needs attention'", $routes);
        self::assertStringContainsString("'status' => 'Needs setup'", $routes);
        self::assertStringContainsString("'status' => 'Ready'", $routes);
        self::assertStringContainsString("'last_synced_at' => \$mailbox['last_synced_at']", $routes);
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class MultiAccountMailboxWiringTest extends TestCase
{
    public function testBootstrapWiresAccountRegistryAggregationAndCoordinator(): void
    {
        $bootstrap = file_get_contents(dirname(__DIR__, 2) . '/bootstrap/mail.php');

        self::assertIsString($bootstrap);
        self::assertStringContainsString('MailboxAccountStore::class', $bootstrap);
        self::assertStringContainsString("storagePath('mail/accounts.json')", $bootstrap);
        self::assertStringContainsString('MailboxSyncCoordinator::class', $bootstrap);
        self::assertStringContainsString("storagePath('mail/cache/' . \$account->id)", $bootstrap);
        self::assertStringContainsString('new AccountCachedMailboxProvider(', $bootstrap);
        self::assertStringContainsString('return new AggregatedMailboxProvider($providers);', $bootstrap);
    }

    public function testScheduledJobSupportsAllAccountsOrOneSelectedAccount(): void
    {
        $job = file_get_contents(dirname(__DIR__, 2) . '/private/jobs/sync-mail.php');

        self::assertIsString($job);
        self::assertStringContainsString("--account=", $job);
        self::assertStringContainsString("--limit=", $job);
        self::assertStringContainsString('syncEnabled($limit)', $job);
        self::assertStringContainsString('syncAccount($accountId, $limit)', $job);
    }
}

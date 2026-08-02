<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class MailSyncJobBootstrapContractTest extends TestCase
{
    public function testSyncJobLoadsMailBindingsBeforeResolvingCoordinator(): void
    {
        $job = file_get_contents(dirname(__DIR__, 2) . '/private/jobs/sync-mail.php');

        self::assertIsString($job);
        self::assertStringContainsString("'/bootstrap/app.php'", $job);
        self::assertStringContainsString("'/bootstrap/mail.php'", $job);
        self::assertLessThan(
            strpos($job, 'make(MailboxSyncCoordinator::class)'),
            strpos($job, "'/bootstrap/mail.php'"),
        );
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class MailSyncJobBootstrapTest extends TestCase
{
    public function testStandaloneSyncJobLoadsMailBindingsBeforeResolvingSynchronizer(): void
    {
        $job = file_get_contents(dirname(__DIR__, 2) . '/private/jobs/sync-mail.php');

        self::assertIsString($job);
        $mailBootstrap = strpos($job, "require dirname(__DIR__, 2) . '/bootstrap/mail.php';");
        $resolution = strpos($job, '$app->make(ImapSynchronizer::class)');

        self::assertNotFalse($mailBootstrap);
        self::assertNotFalse($resolution);
        self::assertLessThan($resolution, $mailBootstrap);
    }
}

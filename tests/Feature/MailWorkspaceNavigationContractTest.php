<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class MailWorkspaceNavigationContractTest extends TestCase
{
    public function testWorkspaceSidebarExposesCorrespondenceDestinations(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail.php');

        self::assertIsString($view);
        self::assertStringContainsString('href="/mail?area=inbox"', $view);
        self::assertStringContainsString('href="/mail?area=inbox#mail-drafts"', $view);
        self::assertStringContainsString('href="/mail/sent"', $view);
        self::assertStringContainsString('>Sent mail<', $view);
        self::assertStringContainsString('href="/mail/archive"', $view);
        self::assertStringContainsString('>Archive<', $view);
    }

    public function testWorkspaceSidebarKeepsNewsletterDestinationsSeparate(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail.php');

        self::assertIsString($view);
        self::assertStringContainsString('href="/mail?area=campaigns"', $view);
        self::assertStringContainsString('href="/mail?area=campaigns#campaign-drafts"', $view);
        self::assertStringContainsString('href="/mail/campaigns"', $view);
        self::assertStringContainsString('>Sent campaigns<', $view);
    }
}

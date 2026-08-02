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

    public function testInboxHeaderOffersAQueuedRefreshAndSidebarGroupsRemainQuiet(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail.php');
        $css = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/mail.css');
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/campaign.php');

        self::assertIsString($view);
        self::assertIsString($css);
        self::assertIsString($routes);
        self::assertStringContainsString('action="/mail/refresh"', $view);
        self::assertStringContainsString('>Get new mail<', $view);
        self::assertStringContainsString('mail-panel-header-title-row', $view);
        self::assertStringContainsString('mail-sidebar .eyebrow', $css);
        self::assertStringContainsString('mail-panel-header-title-row', $css);
        self::assertStringContainsString('$router->post(\'/mail/refresh\'', $routes);
        self::assertStringContainsString('MailboxRefreshRequest::class', $routes);
    }

    public function testWorkspaceAlwaysSuppliesTheComposeErrorContract(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/campaign.php');

        self::assertIsString($routes);
        self::assertStringContainsString("'composeError' => trim((string) (\$request->query['error'] ?? ''))", $routes);
    }
}

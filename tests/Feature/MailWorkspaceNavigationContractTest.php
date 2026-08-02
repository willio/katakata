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

    public function testWorkspaceDetailPanelLoadsSelectedMessagesWithoutBecomingAComposer(): void
    {
        $workspace = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail.php');
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/mail-accounts.php');
        $reader = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/mail-reader.js');
        $partial = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail-message-panel.php');

        self::assertIsString($workspace);
        self::assertIsString($routes);
        self::assertIsString($reader);
        self::assertIsString($partial);
        self::assertStringNotContainsString('selectedDraft', $workspace);
        self::assertStringNotContainsString('Compose mail</h2>', $workspace);
        self::assertStringNotContainsString('mail-compose-form', $workspace);
        self::assertStringContainsString('href="/mail/drafts/', $workspace);
        self::assertStringContainsString('/edit"', $workspace);
        self::assertStringContainsString('data-mail-message-link', $workspace);
        self::assertStringContainsString('data-mail-reader', $workspace);
        self::assertStringContainsString('/assets/js/mail-reader.js', $workspace);
        self::assertStringContainsString("(\$request->query['fragment'] ?? '') === '1'", $routes);
        self::assertStringContainsString("render('mail-message-panel'", $routes);
        self::assertStringContainsString('history.pushState', $reader);
        self::assertStringContainsString('data-mail-message-panel', $partial);
    }

    public function testWorkspaceAvoidsRepeatedLocationLabels(): void
    {
        $workspace = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail.php');

        self::assertIsString($workspace);
        self::assertStringNotContainsString('<p class="eyebrow">Editorial correspondence</p>', $workspace);
        self::assertStringNotContainsString('<p class="eyebrow">Reader mail</p>', $workspace);
        self::assertStringNotContainsString('<p class="eyebrow">Correspondence</p>', $workspace);
        self::assertSame(1, substr_count($workspace, 'Select a message.'));
        self::assertStringNotContainsString('<h2 id="mail-inbox">All accounts</h2>', $workspace);
    }
}

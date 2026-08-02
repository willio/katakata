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

    public function testWorkspaceUsesThreeExplicitColumnsAndMarkerFreeRows(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/mail.css');

        self::assertIsString($css);
        self::assertStringContainsString('.mail-workspace-shell {', $css);
        self::assertStringContainsString('grid-template-columns: minmax(10.5rem, 15rem) minmax(19rem, 26rem) minmax(0, 1fr);', $css);
        self::assertStringContainsString('.mail-sidebar,', $css);
        self::assertStringContainsString('.mail-list-panel { border-right: 1px solid var(--border); }', $css);
        self::assertStringContainsString('.mail-item-list {', $css);
        self::assertStringContainsString('list-style: none;', $css);
        self::assertStringContainsString('grid-template-areas: "subject time" "meta time";', $css);
        self::assertStringContainsString('@media (max-width: 42rem)', $css);
        self::assertStringContainsString('.mail-page:has(.mail-message-panel) .mail-list-panel', $css);
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

    public function testSelectedMessageRendersOnTheServerAndDoesNotChangeTheInboxFilter(): void
    {
        $workspace = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail.php');
        $reader = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/mail-reader.js');
        $message = file_get_contents(dirname(__DIR__, 2) . '/app/Email/Message.php');

        self::assertIsString($workspace);
        self::assertIsString($reader);
        self::assertIsString($message);
        self::assertStringContainsString("\$_GET['message_account']", $workspace);
        self::assertStringContainsString('$selectedMessageRecord = null;', $workspace);
        self::assertStringContainsString('$selectedMessageRecord->text', $workspace);
        self::assertStringContainsString("'/mail?area=inbox&account=' . rawurlencode(\$selectedAccount)", $workspace);
        self::assertStringContainsString("'&message_account='", $workspace);
        self::assertStringContainsString("searchParams.get('message_account')", $reader);
        self::assertStringContainsString('text: $this->text', $message);
        self::assertStringNotContainsString("searchParams.get('account');", $reader);
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

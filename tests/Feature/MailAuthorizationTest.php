<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class MailAuthorizationTest extends TestCase
{
    public function testMailRoutesRequireOwnerOrAdminPermission(): void
    {
        $session = file_get_contents(dirname(__DIR__, 2) . '/app/Auth/Session.php');
        $campaignRoutes = file_get_contents(dirname(__DIR__, 2) . '/routes/campaign.php');
        $mailRoutes = file_get_contents(dirname(__DIR__, 2) . '/routes/mail.php');

        self::assertIsString($session);
        self::assertIsString($campaignRoutes);
        self::assertIsString($mailRoutes);
        self::assertStringContainsString('public function canManageMail(): bool', $session);
        self::assertStringContainsString("return \$this->hasRole('owner', 'admin');", $session);
        self::assertStringContainsString('!$session->canManageMail()', $campaignRoutes);
        self::assertStringContainsString("Response::html('Forbidden.', 403)", $campaignRoutes);
        self::assertStringContainsString('!$session->canManageMail()', $mailRoutes);
        self::assertStringContainsString("Response::html('Forbidden.', 403)", $mailRoutes);
    }

    public function testAllCorrespondenceActionsUseTheMailAuthorizationBoundary(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/mail.php');

        self::assertIsString($routes);
        foreach ([
            '/dashboard/mail',
            '/mail/messages/{id}',
            '/mail/messages/{id}/archive',
            '/mail/messages/{id}/delete',
            '/mail/compose',
            '/mail/messages/{id}/reply',
            '/mail/drafts/{id}',
        ] as $path) {
            self::assertStringContainsString($path, $routes);
        }

        self::assertStringContainsString("foreach (['read' => true, 'unread' => false] as \$action => \$read)", $routes);
        self::assertStringContainsString("'/mail/messages/{id}/' . \$action", $routes);
        self::assertStringContainsString('->deleteLocal($id)', $routes);
        self::assertGreaterThanOrEqual(8, substr_count($routes, '$authorizeMail();'));
        self::assertGreaterThanOrEqual(8, substr_count($routes, 'if ($user instanceof Response)'));
    }

    public function testAttachmentDownloadRouteIsAbsent(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/mail.php');
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail-message.php');

        self::assertIsString($routes);
        self::assertIsString($view);
        self::assertStringNotContainsString('/attachments/{attachmentId}', $routes);
        self::assertStringContainsString('original mailbox application', $view);
        self::assertStringContainsString('Delete cached copy', $view);
    }
}

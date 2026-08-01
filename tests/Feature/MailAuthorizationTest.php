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
            '/mail/messages/{id}/read',
            '/mail/messages/{id}/unread',
            '/mail/messages/{id}/archive',
            '/mail/messages/{messageId}/attachments/{attachmentId}',
            '/mail/compose',
            '/mail/messages/{id}/reply',
            '/mail/drafts/{id}',
        ] as $path) {
            self::assertStringContainsString($path, $routes);
        }
        self::assertGreaterThanOrEqual(8, substr_count($routes, '$authorizeMail()'));
    }

    public function testAttachmentResponsesDisableContentSniffing(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/mail.php');

        self::assertIsString($routes);
        self::assertStringContainsString("'Content-Disposition' => 'attachment; filename=", $routes);
        self::assertStringContainsString("'X-Content-Type-Options' => 'nosniff'", $routes);
    }
}

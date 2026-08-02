<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class SentMailRouteContractTest extends TestCase
{
    public function testSentMailUsesThePrivilegedMailBoundaryAndPrivateStore(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/mail.php');
        $bootstrap = file_get_contents(dirname(__DIR__, 2) . '/bootstrap/mail.php');
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail-sent.php');

        self::assertIsString($routes);
        self::assertIsString($bootstrap);
        self::assertIsString($view);
        self::assertStringContainsString("get('/mail/sent'", $routes);
        self::assertStringContainsString('$authorizeMail()', $routes);
        self::assertStringContainsString('SentMessageStore::class)->recent()', $routes);
        self::assertStringContainsString("storagePath('mail/sent')", $bootstrap);
        self::assertStringContainsString('Sent mail', $view);
        self::assertStringContainsString('after the outbound provider accepts', $view);
    }

    public function testSuccessfulSendRedirectsToSentMail(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/mail.php');

        self::assertIsString($routes);
        self::assertStringContainsString("Response::redirect('/mail/sent', 303)", $routes);
    }
}

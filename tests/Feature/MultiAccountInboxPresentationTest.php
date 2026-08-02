<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class MultiAccountInboxPresentationTest extends TestCase
{
    public function testWorkspaceExposesAccountFiltersAndSourceLabels(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail.php');
        $detail = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail-message.php');
        $css = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/mail.css');

        self::assertIsString($view);
        self::assertIsString($detail);
        self::assertIsString($css);
        self::assertStringContainsString("\$_GET['account'] ?? 'all'", $view);
        self::assertStringContainsString('mail-account-nav', $view);
        self::assertStringContainsString('All accounts', $view);
        self::assertStringContainsString('$message->sourceAccountId === $selectedAccount', $view);
        self::assertStringContainsString('$message->sourceLabel', $view);
        self::assertStringContainsString('Inbox partially available', $view);
        self::assertStringContainsString('$message->sourceLabel', $detail);
        self::assertStringContainsString('account=', $detail);
        self::assertStringContainsString('.mail-account-nav', $css);
    }
}

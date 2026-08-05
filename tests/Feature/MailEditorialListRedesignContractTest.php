<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class MailEditorialListRedesignContractTest extends TestCase
{
    public function testInboxRowsExposeEditorialHierarchyAndStateWithoutChangingNavigation(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail.php');
        $css = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/mail.css');

        self::assertIsString($view);
        self::assertIsString($css);
        self::assertStringContainsString('mail-message-list', $view);
        self::assertStringContainsString('mail-item-sender', $view);
        self::assertStringContainsString('mail-item-snippet', $view);
        self::assertStringContainsString('$message->sourceLabel', $view);
        self::assertStringContainsString('$message->unread ? \'is-unread\' : \'is-read\'', $view);
        self::assertStringContainsString("'/mail?area=inbox&account='", $view);
        self::assertStringContainsString("'&message_account='", $view);
        self::assertStringContainsString('.mail-message-list .is-unread', $css);
        self::assertStringContainsString('a[aria-current="page"]', $css);
    }

    public function testCampaignRowsUseStatusLanguageButKeepSafeReviewFlow(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail.php');

        self::assertIsString($view);
        self::assertStringContainsString('mail-status-pill', $view);
        self::assertStringContainsString('Ready for review', $view);
        self::assertStringContainsString('Review dispatch proof', $view);
        self::assertStringNotContainsString('Send now', $view);
        self::assertStringNotContainsString('Send campaign now', $view);
    }

    public function testMailControlsUseRestrainedRadiusWhileStatusRemainsPillShaped(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/mail.css');

        self::assertIsString($css);
        self::assertStringContainsString('.mail-refresh-button', $css);
        self::assertStringContainsString('border-radius: 6px', $css);
        self::assertStringContainsString('.mail-status-pill', $css);
        self::assertStringContainsString('border-radius: 999px', $css);
    }
}

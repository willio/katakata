<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class Issue35ResponsiveMatrixContractTest extends TestCase
{
    public function testPublicAndOwnerStylesDeclareNarrowLayoutsWithoutHorizontalOverflow(): void
    {
        $home = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/home-redesign.css');
        $dashboard = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/dashboard-redesign.css');
        $posts = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/posts.css');
        $mail = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/mail.css');
        $boundary = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/boundary.css');

        foreach ([$home, $dashboard, $posts, $mail, $boundary] as $css) self::assertIsString($css);

        self::assertStringContainsString('@media (max-width:', $home);
        self::assertStringContainsString('@media (max-width:', $dashboard);
        self::assertStringContainsString('@media (max-width:', $posts);
        self::assertStringContainsString('@media (max-width: 42rem)', $mail);
        self::assertStringContainsString('@media (max-width:', $boundary);
        self::assertStringContainsString('grid-template-columns: 1fr', $home);
        self::assertStringContainsString('overflow-x: hidden', $mail);
    }

    public function testMailNarrowModeUsesSuccessiveListAndDetailViews(): void
    {
        $mail = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/mail.css');

        self::assertIsString($mail);
        self::assertStringContainsString('.mail-workspace-shell { display: block;', $mail);
        self::assertStringContainsString('.mail-page:has(.mail-message-panel) .mail-list-panel', $mail);
        self::assertStringContainsString('.mail-page:not(:has(.mail-message-panel))', $mail);
        self::assertStringContainsString('.mail-draft-editor .mail-compose-actions { position: sticky;', $mail);
    }

    public function testProgressiveReaderAndFocusedEditorsRemainIntact(): void
    {
        $mailView = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail.php');
        $correspondence = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail-draft-editor.php');
        $campaign = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail-campaign-draft.php');

        self::assertIsString($mailView);
        self::assertIsString($correspondence);
        self::assertIsString($campaign);
        self::assertStringContainsString('$selectedMessageRecord', $mailView);
        self::assertStringContainsString('data-mail-reader', $mailView);
        self::assertStringContainsString('data-autosave-url', $correspondence);
        self::assertStringContainsString('expected_version', $correspondence);
        self::assertStringContainsString('data-autosave-url', $campaign);
        self::assertStringContainsString('Confirm and queue', $campaign);
        self::assertStringNotContainsString('Send now', $campaign);
    }

    public function testKeyboardFocusAndReducedMotionRemainSupported(): void
    {
        $boundary = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/boundary.css');
        $site = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/site.css');

        self::assertIsString($boundary);
        self::assertIsString($site);
        self::assertStringContainsString(':focus-visible', $boundary);
        self::assertStringContainsString('outline: 2px solid var(--accent)', $boundary);
        self::assertStringContainsString('@media (prefers-reduced-motion: reduce)', $site);
    }
}

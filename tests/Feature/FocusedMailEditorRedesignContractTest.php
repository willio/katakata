<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class FocusedMailEditorRedesignContractTest extends TestCase
{
    public function testCorrespondenceAndCampaignEditorsShareFocusedShell(): void
    {
        $correspondence = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail-draft-editor.php');
        $campaign = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail-campaign-draft.php');

        self::assertIsString($correspondence);
        self::assertIsString($campaign);
        foreach ([$correspondence, $campaign] as $view) {
            self::assertStringContainsString('/assets/css/focused-editor.css', $view);
            self::assertStringContainsString('focused-mail-editor', $view);
            self::assertStringContainsString('focused-mail-editor-header', $view);
            self::assertStringContainsString('focused-mail-editor-frame', $view);
            self::assertStringNotContainsString('dashboard-header', $view);
            self::assertStringNotContainsString('dashboard-shell', $view);
        }
    }

    public function testSafetyAndAutosaveContractsRemainPresent(): void
    {
        $correspondence = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail-draft-editor.php');
        $campaign = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail-campaign-draft.php');

        self::assertIsString($correspondence);
        self::assertIsString($campaign);
        self::assertStringContainsString('data-autosave-url', $correspondence);
        self::assertStringContainsString('expected_version', $correspondence);
        self::assertStringContainsString('data-autosave-url', $campaign);
        self::assertStringContainsString('expected_version', $campaign);
        self::assertStringContainsString('Review campaign', $campaign);
        self::assertStringContainsString('Confirm and queue', $campaign);
        self::assertStringContainsString('Resume queueing', $campaign);
        self::assertStringNotContainsString('Send now', $campaign);
    }

    public function testFocusedShellDefinesRestrainedDesktopAndStickyMobileActions(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/focused-editor.css');

        self::assertIsString($css);
        self::assertStringContainsString('border-radius: var(--radius-control)', $css);
        self::assertStringContainsString('@media (max-width: 42rem)', $css);
        self::assertStringContainsString('position: sticky', $css);
        self::assertStringContainsString('bottom: 0', $css);
    }
}

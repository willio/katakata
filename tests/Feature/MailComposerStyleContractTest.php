<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class MailComposerStyleContractTest extends TestCase
{
    public function testFocusedCorrespondenceAndCampaignEditorsLoadDedicatedStyles(): void
    {
        $workspace = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail.php');
        $correspondence = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail-draft-editor.php');
        $campaign = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail-campaign-draft.php');

        self::assertIsString($workspace);
        self::assertIsString($correspondence);
        self::assertIsString($campaign);
        self::assertStringContainsString('/assets/css/mail.css', $workspace);
        self::assertStringContainsString('/assets/css/mail.css', $correspondence);
        self::assertStringContainsString('/assets/css/mail.css', $campaign);
        self::assertStringNotContainsString('mail-compose-form', $workspace);
        self::assertStringContainsString('mail-compose-form', $correspondence);
        self::assertStringContainsString('mail-compose-paper', $correspondence);
        self::assertStringContainsString('mail-draft-editor-form', $campaign);
        self::assertStringContainsString('campaign-compose-paper', $campaign);
    }

    public function testComposerStylesCoverFieldsActionsErrorsAndNarrowScreens(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/mail.css');

        self::assertIsString($css);
        foreach ([
            '.mail-compose-shell',
            '.mail-compose-form',
            '.mail-compose-paper',
            '.mail-compose-field input',
            '.mail-compose-body textarea',
            '.mail-compose-error',
            '.mail-compose-actions',
            '@media (max-width: 42rem)',
        ] as $selector) {
            self::assertStringContainsString($selector, $css);
        }
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class MailComposerStyleContractTest extends TestCase
{
    public function testStandaloneAndEmbeddedComposersLoadDedicatedStyles(): void
    {
        $workspace = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail.php');
        $standalone = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail-compose.php');

        self::assertIsString($workspace);
        self::assertIsString($standalone);
        self::assertStringContainsString('/assets/css/mail.css', $workspace);
        self::assertStringContainsString('/assets/css/mail.css', $standalone);
        self::assertStringContainsString('mail-compose-form', $workspace);
        self::assertStringContainsString('mail-compose-paper', $workspace);
        self::assertStringContainsString('mail-compose-form', $standalone);
        self::assertStringContainsString('mail-compose-paper', $standalone);
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

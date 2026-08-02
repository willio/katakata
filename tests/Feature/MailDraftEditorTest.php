<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class MailDraftEditorTest extends TestCase
{
    public function testFullscreenEditorRoutesAndConflictContractExist(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/mail.php');

        self::assertIsString($routes);
        self::assertStringContainsString("/mail/drafts/{id}/edit", $routes);
        self::assertStringContainsString("/mail/drafts/{id}/autosave", $routes);
        self::assertStringContainsString('DraftConflict', $routes);
        self::assertStringContainsString("'current' => \$draftPayload(\$conflict->current)", $routes);
        self::assertStringContainsString("'client_version'", $routes);
        self::assertStringContainsString("Response::json", $routes);
        self::assertStringContainsString(", 409", $routes);
    }

    public function testComposeAndReplyOpenFocusedEditorRoutes(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/mail.php');

        self::assertIsString($routes);
        self::assertGreaterThanOrEqual(3, substr_count($routes, "'/edit'"));
        self::assertStringNotContainsString("'/mail?area=inbox&draft='", $routes);
    }

    public function testEditorUsesSharedAutosaveAndDoesNotExposePrivateInfrastructure(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail-draft-editor.php');
        $script = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/mail-draft-editor.js');

        self::assertIsString($view);
        self::assertIsString($script);
        self::assertStringContainsString('class="editor-page mail-draft-editor"', $view);
        self::assertStringContainsString('/assets/js/editor-autosave.js', $view);
        self::assertStringContainsString('/assets/js/mail-draft-editor.js', $view);
        self::assertStringContainsString('data-autosave-url=', $view);
        self::assertStringContainsString('name="expected_version"', $view);
        self::assertStringContainsString("KatakataAutosave.bind", $script);
        self::assertStringContainsString("fields: ['to', 'subject', 'text']", $script);
        self::assertStringNotContainsString('storage/mail', $view);
        self::assertStringNotContainsString('IMAP_', $view);
        self::assertStringNotContainsString('private/jobs', $view);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class EditorTrashContractTest extends TestCase
{
    public function testRoutesPreserveAuthorizationAndCsrfBoundaries(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/editor.php');
        self::assertIsString($routes);
        self::assertStringContainsString("'/editor/drafts/{slug}/trash'", $routes);
        self::assertStringContainsString("'/editor/posts/{slug}/trash'", $routes);
        self::assertStringContainsString("'/editor/trash/{type}/{id}/restore'", $routes);
        self::assertStringContainsString('!$session->canManageSettings()', $routes);
        self::assertStringContainsString('!$session->validCsrf', $routes);
    }

    public function testEditorUsesAcknowledgedSaveBeforeClose(): void
    {
        $editor = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/editor.js');
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/editor.php');
        $css = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/boundary.css');
        self::assertStringContainsString("title.value = 'Unsaved draft'", $editor);
        self::assertStringContainsString("slugInput.value = 'unsaved-draft'", $editor);
        self::assertStringContainsString("if (await autosave.sync()) window.location.assign('/posts')", $editor);
        self::assertStringContainsString('data-editor-close', $view);
        self::assertStringContainsString('.editor-close-zone:focus-within', $css);
        self::assertStringContainsString('prefers-reduced-motion: reduce', $css);
    }
}

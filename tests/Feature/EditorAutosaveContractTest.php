<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class EditorAutosaveContractTest extends TestCase
{
    public function testPostEditorUsesSharedAutosaveRecoveryAndConflictContract(): void
    {
        $helper = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/editor-autosave.js');
        $editor = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/editor.js');
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/editor.php');

        self::assertIsString($helper);
        self::assertIsString($editor);
        self::assertIsString($view);
        self::assertStringContainsString('window.KatakataAutosave', $helper);
        self::assertStringContainsString('KatakataAutosave.bind', $editor);
        self::assertStringContainsString('localStorage.setItem', $helper);
        self::assertStringContainsString('response.status === 409', $helper);
        self::assertStringContainsString("payload.set('expected_version'", $helper);
        self::assertStringContainsString("fields: ['body', 'title', 'publish_as_newsletter', 'discussion_enabled']", $editor);
        self::assertStringContainsString('/assets/js/editor-autosave.js', $view);
        self::assertLessThan(
            strpos($view, '/assets/js/editor.js'),
            strpos($view, '/assets/js/editor-autosave.js'),
            'The shared helper must load before the post-specific editor script.',
        );
    }

    public function testSharedHelperSerializesOnlySuppliedFieldNames(): void
    {
        $helper = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/editor-autosave.js');

        self::assertIsString($helper);
        self::assertStringContainsString('Object.fromEntries(fields.map', $helper);
        self::assertStringContainsString('fields.includes(target.name)', $helper);
        self::assertStringNotContainsString('Object.fromEntries(new FormData', $helper);
    }
}

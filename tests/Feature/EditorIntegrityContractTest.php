<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use Katakata\View;
use PHPUnit\Framework\TestCase;

final class EditorIntegrityContractTest extends TestCase
{
    public function testDraftCreateRouteGuardsAgainstDuplicateSlugs(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/editor.php');

        self::assertIsString($routes);
        self::assertStringContainsString("\$request->body['original']", $routes);
        self::assertStringContainsString('A draft named [{$slug}] already exists.', $routes);
        self::assertStringContainsString('], 409)', $routes);
        self::assertStringContainsString('$uniqueDraftSlug($repository, $slug)', $routes);
    }

    public function testEditorFormCarriesTheOriginalSlugForUpdateDetection(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/editor.php');

        self::assertIsString($view);
        self::assertStringContainsString('name="original"', $view);
    }

    public function testAutosaveRouteHonorsExpectedVersionWithAConflictResponse(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/editor.php');

        self::assertIsString($routes);
        self::assertStringContainsString("\$request->body['expected_version']", $routes);
        self::assertStringContainsString('hash_equals(DraftVersion::of($existing), $expected)', $routes);
        self::assertStringContainsString("'error' => 'Changed elsewhere.'", $routes);
    }

    public function testPublishFailureRedirectStripsServerPaths(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/editor.php');

        self::assertIsString($routes);
        self::assertStringContainsString('basename($match[1])', $routes);
    }

    public function testEditorViewRendersPublishErrorsEscapedAndVisible(): void
    {
        $html = (new View(dirname(__DIR__, 2) . '/resources/views'))->render('editor', [
            'drafts' => [],
            'draft' => null,
            'csrf' => 'token',
            'notice' => null,
            'error' => 'Published article already exists at [260902_notes.md]. <script>',
            'draftVersion' => '',
            'buttonStyle' => 'regular',
        ]);

        self::assertStringContainsString('role="alert"', $html);
        self::assertStringContainsString('Published article already exists at [260902_notes.md].', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testEditorViewOmitsTheErrorBlockWhenThereIsNoError(): void
    {
        $html = (new View(dirname(__DIR__, 2) . '/resources/views'))->render('editor', [
            'drafts' => [],
            'draft' => null,
            'csrf' => 'token',
            'notice' => null,
            'error' => null,
            'draftVersion' => '',
            'buttonStyle' => 'regular',
        ]);

        self::assertStringNotContainsString('editor-error', $html);
    }
}

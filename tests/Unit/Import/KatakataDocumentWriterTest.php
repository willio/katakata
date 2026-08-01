<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Import;

use Katakata\Content\FrontMatter;
use Katakata\Editorial\AtomicFile;
use Katakata\Editorial\DraftEditor;
use Katakata\Editorial\RevisionStore;
use Katakata\Import\ImportedDocument;
use Katakata\Import\KatakataDocumentWriter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class KatakataDocumentWriterTest extends TestCase
{
    private string $root;
    private string $drafts;
    private KatakataDocumentWriter $writer;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-import-writer-' . bin2hex(random_bytes(6));
        $this->drafts = $this->root . '/drafts';
        $files = new AtomicFile();
        $editor = new DraftEditor(
            $this->drafts,
            $files,
            new RevisionStore($this->root . '/revisions', $files),
        );
        $this->writer = new KatakataDocumentWriter($editor, $this->drafts);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testImportedMetadataRoundTripsThroughTheCanonicalEditorialBoundary(): void
    {
        $result = $this->writer->write($this->document());

        self::assertSame('real-title', $result['slug']);
        self::assertSame($this->drafts . '/real-title.md', $result['path']);
        self::assertFileExists($result['path']);

        $parsed = FrontMatter::parse((string) file_get_contents($result['path']));
        self::assertSame('Real title', $parsed['meta']['title']);
        self::assertSame('Real author', $parsed['meta']['author']);
        self::assertSame('original.docx', $parsed['meta']['source_file']);
        self::assertSame('2024-02-29', $parsed['meta']['source_date']);
        self::assertSame('high', $parsed['meta']['import_confidence_title']);
        self::assertSame('medium', $parsed['meta']['import_confidence_author']);
        self::assertSame('low', $parsed['meta']['import_confidence_date']);
        self::assertArrayHasKey('imported_at', $parsed['meta']);
        self::assertArrayHasKey('updated_at', $parsed['meta']);
        self::assertArrayNotHasKey('import_confidence', $parsed['meta']);
        self::assertSame("Imported body.\n", $parsed['body']);
    }

    public function testDryRunReturnsCanonicalPreviewWithoutWriting(): void
    {
        $result = $this->writer->write($this->document(), true);

        self::assertFileDoesNotExist($result['path']);
        $parsed = FrontMatter::parse($result['content']);
        self::assertSame('Real title', $parsed['meta']['title']);
        self::assertSame('Real author', $parsed['meta']['author']);
        self::assertSame('original.docx', $parsed['meta']['source_file']);
    }

    public function testExistingDraftIsRejectedBeforeTheEditorCanOverwriteIt(): void
    {
        mkdir($this->drafts, 0775, true);
        $path = $this->drafts . '/real-title.md';
        file_put_contents($path, 'keep me');

        try {
            $this->writer->write($this->document());
            self::fail('Expected an existing draft collision to be rejected.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('already exists', $error->getMessage());
        }

        self::assertSame('keep me', file_get_contents($path));
        self::assertSame([], glob($this->root . '/revisions/real-title/*.md') ?: []);
    }

    private function document(): ImportedDocument
    {
        return new ImportedDocument(
            'Real title',
            'Real author',
            '2024-02-29',
            'Imported body.',
            'original.docx',
            ['title' => 'high', 'author' => 'medium', 'date' => 'low'],
        );
    }

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $target = $path . '/' . $entry;
            is_dir($target) ? $this->remove($target) : unlink($target);
        }
        rmdir($path);
    }
}

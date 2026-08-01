<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Import;

use Katakata\Editorial\AtomicFile;
use Katakata\Editorial\DraftEditor;
use Katakata\Editorial\RevisionStore;
use Katakata\Import\DirectoryDocumentImporter;
use Katakata\Import\DocxDocumentParser;
use Katakata\Import\KatakataDocumentWriter;
use Katakata\Import\LegacyDocConverter;
use Katakata\Import\LegacyDocumentImporter;
use PHPUnit\Framework\TestCase;

final class DirectoryDocumentImporterTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-directory-import-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/incoming/nested', 0775, true);
        DocxFixture::minimal($this->root . '/incoming/direct.docx', 'Direct document');
        DocxFixture::minimal($this->root . '/incoming/nested/nested.docx', 'Nested document');
        file_put_contents($this->root . '/incoming/ignored.txt', 'ignored');
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testDryRunCountsPreviewsSeparatelyAndHonorsRecursiveDiscovery(): void
    {
        $result = $this->importer()->import($this->root . '/incoming', null, true, true);

        self::assertSame(0, $result['imported']);
        self::assertSame(2, $result['previewed']);
        self::assertSame(0, $result['failed']);
        self::assertSame(['previewed', 'previewed'], array_column($result['results'], 'status'));
        self::assertDirectoryDoesNotExist($this->root . '/drafts');
    }

    public function testNonRecursiveImportPersistsOnlyDirectDocuments(): void
    {
        $result = $this->importer()->import($this->root . '/incoming');

        self::assertSame(1, $result['imported']);
        self::assertSame(0, $result['previewed']);
        self::assertSame(0, $result['failed']);
        self::assertFileExists($this->root . '/drafts/direct-document.md');
        self::assertFileDoesNotExist($this->root . '/drafts/nested-document.md');
    }

    private function importer(): DirectoryDocumentImporter
    {
        $files = new AtomicFile();
        $drafts = $this->root . '/drafts';
        return new DirectoryDocumentImporter(new LegacyDocumentImporter(
            new DocxDocumentParser(),
            new KatakataDocumentWriter(
                new DraftEditor(
                    $drafts,
                    $files,
                    new RevisionStore($this->root . '/revisions', $files),
                ),
                $drafts,
            ),
            new LegacyDocConverter($this->root . '/conversion'),
        ));
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

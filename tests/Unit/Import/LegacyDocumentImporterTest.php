<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Import;

use Katakata\Content\FrontMatter;
use Katakata\Editorial\AtomicFile;
use Katakata\Editorial\DraftEditor;
use Katakata\Editorial\RevisionStore;
use Katakata\Import\DocxDocumentParser;
use Katakata\Import\KatakataDocumentWriter;
use Katakata\Import\LegacyDocConverter;
use Katakata\Import\LegacyDocumentImporter;
use PHPUnit\Framework\TestCase;

final class LegacyDocumentImporterTest extends TestCase
{
    private string $root;
    private string $originalPath;
    private string|false $originalFixture;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-legacy-import-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/bin', 0775, true);
        $this->originalPath = (string) getenv('PATH');
        $this->originalFixture = getenv('KATAKATA_TEST_DOCX');

        $fixture = DocxFixture::minimal($this->root . '/converted-template.docx');
        putenv('KATAKATA_TEST_DOCX=' . $fixture);
        file_put_contents($this->root . '/bin/soffice', <<<'PHP'
#!/opt/homebrew/bin/php
<?php
$outIndex = array_search('--outdir', $argv, true);
$out = $outIndex === false ? '' : ($argv[$outIndex + 1] ?? '');
$source = $argv[count($argv) - 1] ?? '';
$target = $out . DIRECTORY_SEPARATOR . pathinfo($source, PATHINFO_FILENAME) . '.docx';
exit(copy((string) getenv('KATAKATA_TEST_DOCX'), $target) ? 0 : 1);
PHP);
        chmod($this->root . '/bin/soffice', 0775);
        putenv('PATH=' . $this->root . '/bin:' . $this->originalPath);
    }

    protected function tearDown(): void
    {
        putenv('PATH=' . $this->originalPath);
        $this->originalFixture === false
            ? putenv('KATAKATA_TEST_DOCX')
            : putenv('KATAKATA_TEST_DOCX=' . $this->originalFixture);
        $this->remove($this->root);
    }

    public function testConversionsWithTheSameFilenameUseIndependentWorkspaces(): void
    {
        mkdir($this->root . '/source-a', 0775, true);
        mkdir($this->root . '/source-b', 0775, true);
        $sourceA = $this->root . '/source-a/original.doc';
        $sourceB = $this->root . '/source-b/original.doc';
        file_put_contents($sourceA, 'legacy-a');
        file_put_contents($sourceB, 'legacy-b');
        $converter = new LegacyDocConverter($this->root . '/conversion');

        $convertedA = $converter->convert($sourceA);
        $convertedB = $converter->convert($sourceB);

        self::assertNotSame($convertedA, $convertedB);
        self::assertNotSame(dirname($convertedA), dirname($convertedB));
        self::assertFileExists($convertedA);
        self::assertFileExists($convertedB);

        $converter->cleanup($convertedA);
        $converter->cleanup($convertedB);
        self::assertDirectoryDoesNotExist(dirname($convertedA));
        self::assertDirectoryDoesNotExist(dirname($convertedB));
    }

    public function testLegacyImportPreservesOriginalDocProvenanceUntilParsingCompletes(): void
    {
        $source = $this->root . '/Original source.doc';
        file_put_contents($source, 'legacy');
        $files = new AtomicFile();
        $drafts = $this->root . '/drafts';
        $converter = new LegacyDocConverter($this->root . '/conversion');
        $writer = new KatakataDocumentWriter(
            new DraftEditor(
                $drafts,
                $files,
                new RevisionStore($this->root . '/revisions', $files),
            ),
            $drafts,
        );
        $importer = new LegacyDocumentImporter(new DocxDocumentParser(), $writer, $converter);

        $result = $importer->import($source, null, true);

        self::assertSame('Original source.doc', $result['document']->sourceFile);
        $parsed = FrontMatter::parse($result['content']);
        self::assertSame('Original source.doc', $parsed['meta']['source_file']);
        self::assertSame([], glob($this->root . '/conversion/doc-*') ?: []);
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

<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Import;

use Katakata\Import\DocxDocumentParser;
use PHPUnit\Framework\TestCase;

final class DocxDocumentParserTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-docx-parser-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($this->root);
    }

    public function testItParsesRealDocxMetadataAndDocumentStructure(): void
    {
        $path = DocxFixture::rich($this->root . '/rich.docx');

        $document = (new DocxDocumentParser())->parse($path);

        self::assertSame('Real title', $document->title);
        self::assertSame('Real author', $document->author);
        self::assertSame('2024-02-29', $document->date);
        self::assertSame('rich.docx', $document->sourceFile);
        self::assertSame('high', $document->confidence['title']);
        self::assertSame('medium', $document->confidence['author']);
        self::assertSame('medium', $document->confidence['date']);
        self::assertSame(<<<'MARKDOWN'
## Section **heading**

This is **bold** and *italic*.

- **List item**

> *Quoted line*

Before linked text after.
MARKDOWN . "\n", $document->body);
    }

    public function testItRejectsCalendarInvalidDatesInsteadOfNormalizingThem(): void
    {
        $path = DocxFixture::invalidDate($this->root . '/invalid-date.docx');
        touch($path, strtotime('2025-01-02T12:00:00Z'));

        $document = (new DocxDocumentParser())->parse($path);

        self::assertSame('2025-01-02', $document->date);
        self::assertSame('low', $document->confidence['date']);
        self::assertStringStartsWith("31/02/2024\n\nImported body.", $document->body);
    }

    public function testItRejectsOversizedXmlBeforeExtraction(): void
    {
        $path = DocxFixture::minimal($this->root . '/oversized.docx');
        DocxFixture::claimEntrySize($path, 'word/document.xml', 4 * 1024 * 1024 + 1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('word/document.xml');
        $this->expectExceptionMessage('4194304 bytes');

        (new DocxDocumentParser())->parse($path);
    }

    public function testDocumentBylineOverridesWordCreatorMetadata(): void
    {
        $path = DocxFixture::byline($this->root . '/byline.docx', 'Example Byline', 'Example Editor');

        $document = (new DocxDocumentParser())->parse($path);

        self::assertSame('Example Byline', $document->author);
        self::assertSame('high', $document->confidence['author']);
        self::assertStringNotContainsString('ExampleCategory By Example Byline', $document->body);
    }
}

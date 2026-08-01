<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Import;

use RuntimeException;
use ZipArchive;

final class DocxFixture
{
    public static function rich(string $path): string
    {
        return self::create(
            $path,
            <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="Heading2"/></w:pPr><w:r><w:t>Section heading</w:t></w:r></w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">This is </w:t></w:r>
      <w:r><w:rPr><w:b/></w:rPr><w:t>bold</w:t></w:r>
      <w:r><w:t xml:space="preserve"> and </w:t></w:r>
      <w:r><w:rPr><w:i/></w:rPr><w:t>italic</w:t></w:r>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>List item</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="Quote"/></w:pPr><w:r><w:t>Quoted line</w:t></w:r></w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Before </w:t></w:r>
      <w:hyperlink r:id="rId1"><w:r><w:t>linked text</w:t></w:r></w:hyperlink>
      <w:r><w:t xml:space="preserve"> after.</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML,
            self::core('Real title', 'Real author', '2024-02-29T10:30:00Z'),
        );
    }

    public static function minimal(
        string $path,
        string $title = 'Real title',
        string $author = 'Real author',
    ): string
    {
        return self::create(
            $path,
            <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body><w:p><w:r><w:t>Imported body.</w:t></w:r></w:p></w:body>
</w:document>
XML,
            self::core($title, $author, '2024-02-29T10:30:00Z'),
        );
    }

    public static function invalidDate(string $path): string
    {
        return self::create(
            $path,
            <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>31/02/2024</w:t></w:r></w:p>
    <w:p><w:r><w:t>Imported body.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
            self::core('Invalid date example', 'Real author', null),
        );
    }

    private static function create(string $path, string $documentXml, string $coreXml): string
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create DOCX fixture directory [{$directory}].");
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Unable to create DOCX fixture [{$path}].");
        }

        $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>
XML);
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->addFromString('docProps/core.xml', $coreXml);
        $zip->close();

        return $path;
    }

    private static function core(string $title, string $author, ?string $created): string
    {
        $createdXml = $created === null ? '' : '<dcterms:created xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:created>';

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dc:title>{$title}</dc:title>
  <dc:creator>{$author}</dc:creator>
  {$createdXml}
</cp:coreProperties>
XML;
    }
}

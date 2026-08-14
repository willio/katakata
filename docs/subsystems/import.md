# Legacy Document Import

Katakata can reconcile `.docx` and legacy `.doc` files into canonical drafts without creating a second editorial write path. Import is an ingestion aid: the resulting Markdown draft is the only durable authored copy and must be reviewed before publication.

## Commands

```bash
php bin/katakata import:document <path> [--author=name] [--dry-run]
php bin/katakata import:directory <path> [--recursive] [--author=name] [--dry-run]
```

Directory imports are non-recursive unless `--recursive` is present. Unsupported files are ignored. Each supported file is isolated: one failure is reported without stopping the remaining batch. The summary counts persisted imports, dry-run previews, and failures separately.

`--author` is a fallback for documents without usable author metadata. An explicit document byline remains preferred over Word core-properties creator metadata, because the latter may identify an editor or publisher rather than the contributor.

## Editorial boundary

`DocxDocumentParser` produces an `ImportedDocument`. `LegacyDocumentImporter` coordinates parsing and conversion, and `KatakataDocumentWriter` derives a lowercase URL-safe slug from the detected title. Real imports reject an existing `content/drafts/<slug>.md` before mutation and then call `DraftEditor::save()`; import code never writes canonical Markdown directly.

Dry runs return the complete proposed Markdown and target path without creating a draft. The target filename is `<title-slug>.md`. The source date is provenance, not draft identity, so it never prefixes the filename.

## Flat provenance metadata

Imported drafts use only the flat front matter supported by ADR 0005:

```yaml
source_file: original.docx
source_date: 2024-02-29
original_category: ExampleCategory
original_published: 2024-02-29
imported_at: 2026-08-01T10:00:00+07:00
import_confidence_title: high
import_confidence_author: medium
import_confidence_date: low
```

The canonical title and author are stored as ordinary `title` and `author` fields. Confidence values describe the import heuristic and do not replace those values.
When a source file is inside a named legacy category directory, the directory name is retained as `original_category`; `original_published` mirrors the extracted source date. These are provenance fields, not navigable tags.

## DOCX interpretation

DOCX files are read as ZIP archives. Before extraction, each XML entry's declared uncompressed size is inspected and limited to exactly 4 MiB (4,194,304 bytes). Larger entries are rejected without expansion. For authors, the parser checks an explicit byline in the first eight paragraphs (including `ExampleCategory By ...`, even when a short import artifact precedes it) before Word core-properties creator metadata, then the CLI fallback. This preserves the contributor when the core-properties creator is an editor or publisher. It preserves headings, list items, quote paragraphs, bold and italic runs—including emphasis inside headings, lists, and quotes—and text inside hyperlink elements. It imports hyperlink text but does not currently emit a Markdown URL because relationship targets are not part of the supported contract.

Dates are accepted only when PHP reports no parsing warnings or errors. Calendar-invalid values such as `31/02/2024` remain body text and the parser continues to the filename or file-modification fallback.

## Legacy `.doc` conversion

Legacy Word files require `soffice` or `libreoffice` on `PATH`. Each conversion uses a unique owner-only (`0700`) directory beneath `storage/tmp/import`; the converted DOCX remains there until parsing finishes, then the whole workspace is removed. A cleanup failure is surfaced as an import failure rather than ignored. The resulting draft records the original `.doc` basename, not the temporary `.docx` filename, and an undated conversion falls back to the original `.doc` filename and modification time rather than temporary DOCX provenance.

DOCX import requires PHP's DOM and ZIP extensions. LibreOffice is optional unless `.doc` files are imported.

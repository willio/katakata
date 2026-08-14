<?php

declare(strict_types=1);

namespace Katakata\Import;

use DateTimeImmutable;
use Katakata\Editorial\Document;
use Katakata\Editorial\DraftEditor;
use RuntimeException;

final class KatakataDocumentWriter
{
    public function __construct(
        private readonly DraftEditor $editor,
        private readonly string $draftPath,
    ) {
    }

    /** @return array{path:string,slug:string,content:string} */
    public function write(ImportedDocument $document, bool $dryRun = false): array
    {
        $slug = $this->slug($document->title);
        if ($slug === '') {
            $slug = 'imported-' . substr(hash('sha256', $document->sourceFile), 0, 12);
        }

        $path = rtrim($this->draftPath, '/\\') . DIRECTORY_SEPARATOR . $slug . '.md';
        $meta = $this->metadata($document);

        if ($dryRun) {
            return [
                'path' => $path,
                'slug' => $slug,
                'content' => Document::markdown(['title' => trim($document->title)] + $meta, $document->body),
            ];
        }
        if (is_file($path)) {
            throw new RuntimeException("Draft already exists [{$path}].");
        }

        $path = $this->editor->save($slug, $document->title, $document->body, $meta);
        $content = file_get_contents($path);
        if (!is_string($content)) {
            throw new RuntimeException("Unable to read imported draft [{$path}].");
        }

        return ['path' => $path, 'slug' => $slug, 'content' => $content];
    }

    /** @return array<string, string> */
    private function metadata(ImportedDocument $document): array
    {
        $metadata = [
            'author' => $document->author,
            'status' => 'draft',
            'source_file' => $document->sourceFile,
            'source_date' => $document->date,
            'imported_at' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
            'import_confidence_title' => $document->confidence['title'] ?? 'low',
            'import_confidence_author' => $document->confidence['author'] ?? 'low',
            'import_confidence_date' => $document->confidence['date'] ?? 'low',
        ];

        if ($document->originalCategory !== null) {
            $metadata['original_category'] = $document->originalCategory;
            $metadata['original_published'] = $document->date;
        }

        return $metadata;
    }

    private function slug(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }
}

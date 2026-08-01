<?php

declare(strict_types=1);

namespace Katakata\Console;

use Katakata\Import\DirectoryDocumentImporter;
use Katakata\Import\LegacyDocumentImporter;

trait ImportCommands
{
    /** @param array<int, string> $args */
    private function importDocument(array $args): int
    {
        $path = $args[0] ?? '';
        if ($path === '') {
            return $this->usage('import:document <path> [--author=name] [--dry-run]');
        }

        $options = $this->importOptions(array_slice($args, 1), false);
        if ($options === null) {
            return $this->usage('import:document <path> [--author=name] [--dry-run]');
        }

        try {
            $result = $this->app->make(LegacyDocumentImporter::class)->import(
                $path,
                $options['author'],
                $options['dry_run'],
            );
            $document = $result['document'];
            fwrite(STDOUT, ($options['dry_run'] ? 'Previewed' : 'Imported') . " document.\n");
            fwrite(STDOUT, "Title: {$document->title}\n");
            fwrite(STDOUT, "Author: {$document->author} ({$document->confidence['author']})\n");
            fwrite(STDOUT, "Date: {$document->date} ({$document->confidence['date']})\n");
            fwrite(STDOUT, "Draft: {$result['path']}\n");
            if ($options['dry_run']) {
                fwrite(STDOUT, "\n{$result['content']}");
            }
            return 0;
        } catch (\Throwable $error) {
            fwrite(STDERR, $error->getMessage() . "\n");
            return 1;
        }
    }

    /** @param array<int, string> $args */
    private function importDirectory(array $args): int
    {
        $path = $args[0] ?? '';
        if ($path === '') {
            return $this->usage('import:directory <path> [--recursive] [--author=name] [--dry-run]');
        }

        $options = $this->importOptions(array_slice($args, 1), true);
        if ($options === null) {
            return $this->usage('import:directory <path> [--recursive] [--author=name] [--dry-run]');
        }

        try {
            $result = $this->app->make(DirectoryDocumentImporter::class)->import(
                $path,
                $options['author'],
                $options['dry_run'],
                $options['recursive'],
            );
            foreach ($result['results'] as $item) {
                $detail = $item['error'] ?? $item['path'] ?? '';
                fwrite(STDOUT, ucfirst($item['status']) . ": {$item['source']}{$this->importDetail($detail)}\n");
            }
            fwrite(STDOUT, "Imported: {$result['imported']}\n");
            fwrite(STDOUT, "Previewed: {$result['previewed']}\n");
            fwrite(STDOUT, "Failed: {$result['failed']}\n");
            return $result['failed'] > 0 ? 1 : 0;
        } catch (\Throwable $error) {
            fwrite(STDERR, $error->getMessage() . "\n");
            return 1;
        }
    }

    /**
     * @param list<string> $arguments
     * @return array{author:?string,dry_run:bool,recursive:bool}|null
     */
    private function importOptions(array $arguments, bool $allowRecursive): ?array
    {
        $options = ['author' => null, 'dry_run' => false, 'recursive' => false];
        foreach ($arguments as $argument) {
            if ($argument === '--dry-run') {
                $options['dry_run'] = true;
            } elseif ($allowRecursive && $argument === '--recursive') {
                $options['recursive'] = true;
            } elseif (str_starts_with($argument, '--author=')) {
                $author = trim(substr($argument, 9));
                $options['author'] = $author === '' ? null : $author;
            } else {
                return null;
            }
        }
        return $options;
    }

    private function importDetail(string $detail): string
    {
        return $detail === '' ? '' : " -> {$detail}";
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Import;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class DirectoryDocumentImporter
{
    public function __construct(private readonly LegacyDocumentImporter $documents)
    {
    }

    /**
     * @return array{imported:int,previewed:int,failed:int,results:list<array{source:string,status:string,path:?string,error:?string}>}
     */
    public function import(
        string $directory,
        ?string $author = null,
        bool $dryRun = false,
        bool $recursive = false,
    ): array {
        if (!is_dir($directory)) {
            throw new RuntimeException("Import directory not found [{$directory}].");
        }

        $files = $recursive
            ? $this->recursiveFiles($directory)
            : $this->directFiles($directory);

        $results = [];
        $imported = 0;
        $previewed = 0;
        $failed = 0;

        foreach ($files as $path) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($extension, ['doc', 'docx'], true)) {
                continue;
            }

            try {
                $result = $this->documents->import($path, $author, $dryRun);
                $results[] = [
                    'source' => $path,
                    'status' => $dryRun ? 'previewed' : 'imported',
                    'path' => $result['path'],
                    'error' => null,
                ];
                $dryRun ? $previewed++ : $imported++;
            } catch (\Throwable $error) {
                $results[] = [
                    'source' => $path,
                    'status' => 'failed',
                    'path' => null,
                    'error' => $error->getMessage(),
                ];
                $failed++;
            }
        }

        return [
            'imported' => $imported,
            'previewed' => $previewed,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    /** @return list<string> */
    private function directFiles(string $directory): array
    {
        $files = [];
        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $item;
            if (is_file($path)) {
                $files[] = $path;
            }
        }
        sort($files);
        return $files;
    }

    /** @return list<string> */
    private function recursiveFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);
        return $files;
    }
}

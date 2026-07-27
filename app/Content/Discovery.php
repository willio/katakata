<?php

declare(strict_types=1);

namespace Katakata\Content;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Finds content files on disk.
 *
 * Discovery only locates files — it never parses or validates them.
 * That happens downstream in the Repository, per the Content
 * Pipeline: Filesystem → Discovery → Validation → Front Matter →
 * Markdown → Post Object → Repository.
 */
final class Discovery
{
    public function __construct(
        private readonly string $postsPath,
        private readonly string $draftsPath,
        private readonly string $authorsPath,
        private readonly string $assetsPath,
    ) {
    }

    /**
     * @return array<int, string> absolute paths, e.g. .../posts/2026/01/260115_hello-world.md
     */
    public function posts(): array
    {
        return $this->glob($this->postsPath . '/*/*/*.md');
    }

    /**
     * @return array<int, string>
     */
    public function drafts(): array
    {
        return $this->glob($this->draftsPath . '/*.md');
    }

    /**
     * @return array<int, string>
     */
    public function authors(): array
    {
        return $this->glob($this->authorsPath . '/*.md');
    }

    /**
     * @return array<int, string>
     */
    public function assets(): array
    {
        if (!is_dir($this->assetsPath)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->assetsPath, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && !str_starts_with($fileInfo->getFilename(), '.')) {
                $files[] = $fileInfo->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return array<int, string>
     */
    private function glob(string $pattern): array
    {
        $matches = glob($pattern) ?: [];
        sort($matches);

        return $matches;
    }
}

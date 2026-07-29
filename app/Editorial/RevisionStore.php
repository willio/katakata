<?php

declare(strict_types=1);

namespace Katakata\Editorial;

use DateTimeImmutable;
use RuntimeException;

final class RevisionStore
{
    public function __construct(
        private readonly string $path,
        private readonly AtomicFile $files,
    ) {
    }

    public function capture(string $slug, string $source, ?DateTimeImmutable $at = null): ?string
    {
        if (!is_file($source)) {
            return null;
        }

        $contents = file_get_contents($source);
        if ($contents === false) {
            throw new RuntimeException("Unable to read [{$source}] for revision.");
        }

        $at ??= new DateTimeImmutable();
        $id = $at->format('YmdHis.u') . '-' . substr(hash('sha256', $contents), 0, 12);
        $target = $this->path . '/' . $slug . '/' . $id . '.md';
        $this->files->write($target, $contents);

        return $target;
    }

    /** @return array<int, string> */
    public function all(string $slug): array
    {
        $files = glob($this->path . '/' . $slug . '/*.md') ?: [];
        rsort($files, SORT_STRING);

        return $files;
    }
}

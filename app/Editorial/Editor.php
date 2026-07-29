<?php

declare(strict_types=1);

namespace Katakata\Editorial;

use RuntimeException;

final class Editor
{
    public function __construct(
        private readonly AtomicFile $files,
        private readonly RevisionStore $revisions,
    ) {
    }

    public function edit(string $slug, string $path, string $command): void
    {
        if (!is_file($path)) {
            throw new RuntimeException("Draft [{$slug}] does not exist.");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Unable to read draft [{$slug}].");
        }

        $temporary = tempnam(sys_get_temp_dir(), 'katakata-edit-');
        if ($temporary === false) {
            throw new RuntimeException('Unable to create editor file.');
        }

        file_put_contents($temporary, $contents, LOCK_EX);
        $parts = str_getcsv($command, ' ', '"', '\\');
        $shell = implode(' ', array_map('escapeshellarg', array_filter($parts, 'strlen')));
        passthru($shell . ' ' . escapeshellarg($temporary), $status);

        try {
            if ($status !== 0) {
                throw new RuntimeException("Editor exited with status [{$status}].");
            }

            $edited = file_get_contents($temporary);
            if ($edited === false) {
                throw new RuntimeException('Unable to read editor result.');
            }

            if ($edited !== $contents) {
                $this->revisions->capture($slug, $path);
                $this->files->write($path, $edited);
            }
        } finally {
            unlink($temporary);
        }
    }
}

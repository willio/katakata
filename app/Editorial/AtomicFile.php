<?php

declare(strict_types=1);

namespace Katakata\Editorial;

use RuntimeException;

final class AtomicFile
{
    public function write(string $path, string $contents): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create directory [{$directory}].");
        }

        $temporary = tempnam($directory, '.katakata-');
        if ($temporary === false) {
            throw new RuntimeException("Unable to create a temporary file in [{$directory}].");
        }

        try {
            if (file_put_contents($temporary, $contents, LOCK_EX) === false || !rename($temporary, $path)) {
                throw new RuntimeException("Unable to atomically write [{$path}].");
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}

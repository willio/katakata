<?php

declare(strict_types=1);

namespace Katakata\Support;

/**
 * A minimal .env file loader.
 *
 * Deliberately dependency-free: it parses simple KEY=VALUE lines and
 * nothing more. This is not meant to replace a full-featured dotenv
 * library if the project ever needs one — it exists so local
 * development and deployment work without requiring Composer.
 */
final class DotEnv
{
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");

            if ($key === '' || array_key_exists($key, $_ENV)) {
                continue;
            }

            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

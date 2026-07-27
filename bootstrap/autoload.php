<?php

declare(strict_types=1);

/**
 * A minimal PSR-4 autoloader for the Katakata and Katakata\Tests
 * namespaces.
 *
 * The application itself never depends on Composer's autoloader to
 * run — only optional developer tooling (PHPUnit) does. This keeps
 * `php public/index.php` and `php bin/katakata` working even before
 * `composer install` has ever been run.
 */

$katakataAutoloadMap = [
    'Katakata\\Tests\\' => dirname(__DIR__) . '/tests/',
    'Katakata\\' => dirname(__DIR__) . '/app/',
];

spl_autoload_register(static function (string $class) use ($katakataAutoloadMap): void {
    foreach ($katakataAutoloadMap as $prefix => $baseDir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $path = $baseDir . str_replace('\\', '/', $relative) . '.php';

        if (is_file($path)) {
            require $path;
        }

        return;
    }
});

<?php

declare(strict_types=1);

if (!function_exists('env')) {
    /**
     * Read an environment variable, with a typed fallback default.
     *
     * A present-but-empty value (for example `KEY=` in .env) counts as
     * unset so documented fallbacks such as APP_KEY still apply.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => $value,
        };
    }
}

if (!function_exists('e')) {
    /**
     * Escape a value for safe interpolation into an HTML view.
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

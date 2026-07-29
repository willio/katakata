<?php

declare(strict_types=1);

namespace Katakata;

use RuntimeException;
use Throwable;

final class View
{
    public function __construct(private readonly string $basePath)
    {
    }

    public static function forApplication(Application $app): self
    {
        return new self($app->basePath('resources/views'));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = []): string
    {
        $path = $this->resolve($view);

        return (static function (string $__path, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();

            try {
                require $__path;

                return (string) ob_get_clean();
            } catch (Throwable $exception) {
                ob_end_clean();

                throw $exception;
            }
        })($path, $data);
    }

    private function resolve(string $view): string
    {
        if (!preg_match('#^[a-zA-Z0-9_/-]+$#', $view)) {
            throw new RuntimeException("Invalid view name [{$view}].");
        }

        $path = $this->basePath . DIRECTORY_SEPARATOR . $view . '.php';

        if (!is_file($path)) {
            throw new RuntimeException("View [{$view}] not found.");
        }

        return $path;
    }
}

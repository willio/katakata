<?php

declare(strict_types=1);

namespace Katakata;

use RuntimeException;

/**
 * The Katakata Application Kernel.
 *
 * A single, framework-independent bootstrap shared by the HTTP front
 * controller, the CLI console, background workers, and the test
 * suite. The Kernel is intentionally small and is responsible for
 * exactly three things:
 *
 *   1. Resolving the application's base paths.
 *   2. Loading immutable configuration.
 *   3. Providing a minimal service container.
 *
 * Business logic never lives here. Controllers, commands, and
 * workers consume the Kernel — the Kernel does not know about them.
 */
final class Application extends Container
{
    private readonly string $basePath;
    private readonly Config $config;
    private bool $booted = false;

    public function __construct(string $basePath)
    {
        parent::__construct();

        $this->basePath = rtrim($basePath, '/\\');
        $this->config = new Config();

        $this->instance(self::class, $this);
        $this->instance(Config::class, $this->config);
    }

    public function basePath(string $path = ''): string
    {
        return $path === ''
            ? $this->basePath
            : $this->basePath . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }

    public function configPath(string $path = ''): string
    {
        return $this->basePath('config' . ($path === '' ? '' : '/' . $path));
    }

    public function contentPath(string $path = ''): string
    {
        return $this->basePath('content' . ($path === '' ? '' : '/' . $path));
    }

    public function storagePath(string $path = ''): string
    {
        return $this->basePath('storage' . ($path === '' ? '' : '/' . $path));
    }

    public function routesPath(string $path = ''): string
    {
        return $this->basePath('routes' . ($path === '' ? '' : '/' . $path));
    }

    /**
     * Boot the application: load every config/*.php file and freeze
     * the resulting configuration. Configuration is immutable after
     * this point, per the Master Specification's "Runtime" principle.
     */
    public function boot(): self
    {
        if ($this->booted) {
            return $this;
        }

        foreach (glob($this->configPath('*.php')) ?: [] as $file) {
            $key = basename($file, '.php');
            $values = require $file;

            if (!is_array($values)) {
                throw new RuntimeException("Configuration file [{$file}] must return an array.");
            }

            $this->config->set($key, $values);
        }

        $this->config->freeze();
        $this->booted = true;

        return $this;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    public function config(): Config
    {
        return $this->config;
    }
}

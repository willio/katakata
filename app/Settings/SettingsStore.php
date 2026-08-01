<?php

declare(strict_types=1);

namespace Katakata\Settings;

use Katakata\Editorial\AtomicFile;
use RuntimeException;

final class SettingsStore
{
    /** @var array<string, list<string>> */
    private const KEYS = [
        'publication' => ['name', 'description', 'default_author'],
        'newsletter' => ['sender_label', 'publish_by_default'],
        'discussion' => ['provider', 'enabled_by_default'],
        'analytics' => ['dashboard_period'],
        'appearance' => ['theme'],
    ];

    public function __construct(
        private readonly string $path,
        private readonly AtomicFile $files,
    ) {
    }

    /** @return array<string, array<string, scalar|null>> */
    public function all(): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        $contents = file_get_contents($this->path);
        if ($contents === false) {
            throw new RuntimeException("Unable to read application settings [{$this->path}].");
        }

        try {
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new RuntimeException('Application settings are invalid JSON.', 0, $error);
        }

        if (!is_array($data)) {
            throw new RuntimeException('Application settings must contain an object.');
        }

        return $this->validateDocument($data);
    }

    /** @return array<string, scalar|null> */
    public function section(string $section): array
    {
        $this->assertKnownSection($section);

        return $this->all()[$section] ?? [];
    }

    /** @param array<string, scalar|null> $values */
    public function updateSection(string $section, array $values): void
    {
        $this->assertKnownSection($section);
        $validated = $this->validateSection($section, $values);
        $settings = $this->all();
        $settings[$section] = $validated;

        $this->files->write(
            $this->path,
            json_encode(
                $settings,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ) . "\n",
        );
        @chmod($this->path, 0600);
    }

    private function assertKnownSection(string $section): void
    {
        if (!array_key_exists($section, self::KEYS)) {
            throw new RuntimeException("Unknown settings section [{$section}].");
        }
    }

    /**
     * @param array<mixed> $document
     * @return array<string, array<string, scalar|null>>
     */
    private function validateDocument(array $document): array
    {
        $validated = [];
        foreach ($document as $section => $values) {
            if (!is_string($section)) {
                throw new RuntimeException('Application settings section names must be strings.');
            }
            $this->assertKnownSection($section);
            if (!is_array($values)) {
                throw new RuntimeException("Settings section [{$section}] must contain an object.");
            }
            $validated[$section] = $this->validateSection($section, $values);
        }

        return $validated;
    }

    /**
     * @param array<mixed> $values
     * @return array<string, scalar|null>
     */
    private function validateSection(string $section, array $values): array
    {
        $validated = [];
        foreach ($values as $key => $value) {
            if (!is_string($key) || !in_array($key, self::KEYS[$section], true)) {
                $label = is_string($key) ? $key : get_debug_type($key);
                throw new RuntimeException("Unknown setting [{$section}.{$label}].");
            }
            if (!is_scalar($value) && $value !== null) {
                throw new RuntimeException("Setting [{$section}.{$key}] must be scalar or null.");
            }
            $validated[$key] = $value;
        }

        return $validated;
    }
}

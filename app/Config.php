<?php

declare(strict_types=1);

namespace Katakata;

use RuntimeException;

/**
 * Immutable configuration repository.
 *
 * Configuration may only be written during boot. Once frozen, any
 * attempt to mutate it throws — deliberately, per the Master
 * Specification: "Configuration is immutable after startup."
 */
final class Config
{
    /** @var array<string, mixed> */
    private array $items = [];
    private bool $frozen = false;

    public function set(string $key, mixed $value): void
    {
        if ($this->frozen) {
            throw new RuntimeException('Configuration is immutable after boot.');
        }

        $this->items[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function freeze(): void
    {
        $this->frozen = true;
    }

    public function isFrozen(): bool
    {
        return $this->frozen;
    }
}

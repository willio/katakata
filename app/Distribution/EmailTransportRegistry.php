<?php

declare(strict_types=1);

namespace Katakata\Distribution;

use Closure;
use RuntimeException;

final class EmailTransportRegistry
{
    /** @var array<string, Closure(): EmailTransport> */
    private array $factories = [];

    /** @var array<string, EmailTransport> */
    private array $resolved = [];

    public function register(string $name, Closure $factory): self
    {
        $name = $this->normalize($name);
        if (isset($this->factories[$name])) {
            throw new RuntimeException("Mail transport [{$name}] is already registered.");
        }

        $this->factories[$name] = $factory;

        return $this;
    }

    public function resolve(string $name): EmailTransport
    {
        $name = $this->normalize($name);
        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        $factory = $this->factories[$name] ?? null;
        if ($factory === null) {
            throw new RuntimeException("Unsupported mail transport [{$name}].");
        }

        $transport = $factory();
        if (!$transport instanceof EmailTransport) {
            throw new RuntimeException("Mail transport [{$name}] must implement EmailTransport.");
        }

        return $this->resolved[$name] = $transport;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->factories);
    }

    private function normalize(string $name): string
    {
        $name = strtolower(trim($name));
        if (preg_match('/^[a-z][a-z0-9_-]*$/', $name) !== 1) {
            throw new RuntimeException("Invalid mail transport name [{$name}].");
        }

        return $name;
    }
}

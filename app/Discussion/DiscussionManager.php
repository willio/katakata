<?php

declare(strict_types=1);

namespace Katakata\Discussion;

use InvalidArgumentException;

final class DiscussionManager
{
    /** @var array<string, DiscussionProvider> */
    private array $providers = [];

    public function __construct(
        private readonly DiscussionProvider $fallback,
        DiscussionProvider ...$providers,
    ) {
        $this->register($fallback);

        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }

    public function register(DiscussionProvider $provider): void
    {
        $key = trim($provider->key());
        if ($key === '') {
            throw new InvalidArgumentException('Discussion provider key cannot be empty.');
        }

        $this->providers[$key] = $provider;
    }

    public function resolve(?string $key): DiscussionProvider
    {
        $provider = $this->providers[trim((string) $key)] ?? null;

        return $provider !== null && $provider->isAvailable() ? $provider : $this->fallback;
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Distribution;

final class Delivery
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public readonly string $channel,
        public readonly string $status,
        public readonly ?string $error = null,
        public readonly array $metadata = [],
    ) {
    }

    /** @param array<string, mixed> $metadata */
    public static function delivered(string $channel, array $metadata): self
    {
        return new self($channel, 'delivered', null, $metadata);
    }

    public static function failed(string $channel, \Throwable $error): self
    {
        return new self($channel, 'failed', $error->getMessage());
    }

    public function succeeded(): bool
    {
        return $this->status === 'delivered';
    }
}

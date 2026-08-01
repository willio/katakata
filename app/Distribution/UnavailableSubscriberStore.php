<?php

declare(strict_types=1);

namespace Katakata\Distribution;

use RuntimeException;

final class UnavailableSubscriberStore extends SubscriberStore
{
    public function __construct(private readonly string $reason = 'Newsletter is not configured.')
    {
    }

    public function request(string $email, ?\DateTimeImmutable $now = null): array
    {
        throw new RuntimeException($this->reason);
    }

    public function confirm(string $token, ?\DateTimeImmutable $now = null): array
    {
        throw new RuntimeException($this->reason);
    }

    public function unsubscribe(string $token, ?\DateTimeImmutable $now = null): array
    {
        throw new RuntimeException($this->reason);
    }

    public function suppress(string $email, string $reason, ?\DateTimeImmutable $now = null): bool
    {
        return false;
    }

    public function active(): array
    {
        return [];
    }

    public function deliverable(): array
    {
        return [];
    }
}

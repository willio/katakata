<?php

declare(strict_types=1);

namespace Katakata\Analytics;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final readonly class VisitorHasher
{
    public function __construct(private string $secret)
    {
    }

    public function hash(string $ipAddress, string $userAgent, ?DateTimeImmutable $at = null): string
    {
        if ($this->secret === '') {
            throw new RuntimeException('ANALYTICS_SECRET or APP_KEY must be configured.');
        }

        $date = ($at ?? new DateTimeImmutable('now'))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d');

        return substr(hash('sha256', $this->secret . '|' . $date . '|' . $ipAddress . '|' . $userAgent), 0, 16);
    }
}

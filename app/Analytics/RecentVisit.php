<?php

declare(strict_types=1);

namespace Katakata\Analytics;

use DateTimeImmutable;

final readonly class RecentVisit
{
    public function __construct(
        public DateTimeImmutable $at,
        public string $path,
        public ?string $referrer,
        public ?string $region,
    ) {
    }
}

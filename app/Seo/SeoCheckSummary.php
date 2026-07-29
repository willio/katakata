<?php

declare(strict_types=1);

namespace Katakata\Seo;

use DateTimeImmutable;

final readonly class SeoCheckSummary
{
    /** @param array<int, SeoIssue> $issues */
    public function __construct(
        public DateTimeImmutable $checkedAt,
        public array $issues,
    ) {
    }

    public function passed(): bool
    {
        return $this->issues === [];
    }
}

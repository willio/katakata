<?php

declare(strict_types=1);

namespace Katakata\Seo;

final readonly class SeoIssue
{
    public function __construct(
        public string $slug,
        public string $type,
        public string $message,
    ) {
    }
}

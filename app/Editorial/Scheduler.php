<?php

declare(strict_types=1);

namespace Katakata\Editorial;

use DateTimeImmutable;
use Katakata\Content\Collection;
use Katakata\Content\Draft;

final class Scheduler
{
    /** @param Collection<Draft> $drafts @return array<int, Draft> */
    public function due(Collection $drafts, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();

        return array_values(array_filter($drafts->all(), static function (Draft $draft) use ($now): bool {
            if (($draft->meta['status'] ?? null) !== 'scheduled' || !is_string($draft->meta['publish_at'] ?? null)) {
                return false;
            }

            try {
                return new DateTimeImmutable($draft->meta['publish_at']) <= $now;
            } catch (\Exception) {
                return false;
            }
        }));
    }
}

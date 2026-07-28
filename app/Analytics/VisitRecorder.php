<?php

declare(strict_types=1);

namespace Katakata\Analytics;

use DateTimeImmutable;
use Katakata\Http\Request;
use Throwable;

final readonly class VisitRecorder
{
    public function __construct(
        private AnalyticsStore $store,
        private VisitorHasher $hasher,
    ) {
    }

    public function record(Request $request, ?string $region = null, ?DateTimeImmutable $at = null): bool
    {
        try {
            $ipAddress = $request->server['REMOTE_ADDR'] ?? '';
            $userAgent = $request->server['HTTP_USER_AGENT'] ?? '';
            if ($ipAddress === '' || $userAgent === '') {
                return false;
            }

            $this->store->record(
                path: $request->path,
                referrer: $this->optional($request->server['HTTP_REFERER'] ?? null),
                region: $this->optional($region),
                visitorHash: $this->hasher->hash($ipAddress, $userAgent, $at),
                at: $at,
            );

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function optional(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 2048);
    }
}

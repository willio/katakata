<?php

declare(strict_types=1);

namespace Katakata\Mail;

use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;
use RuntimeException;

final class CampaignRetryService
{
    public function __construct(
        private readonly string $queuePath,
        private readonly AtomicFile $files,
    ) {
    }

    /** @return array{retried: int, skipped: int, abandoned: int} */
    public function retry(Campaign $campaign, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();
        $result = ['retried' => 0, 'skipped' => 0, 'abandoned' => 0];

        foreach (glob(rtrim($this->queuePath, '/') . '/*.json') ?: [] as $path) {
            $item = $this->read($path);
            $key = (string) ($item['idempotency_key'] ?? '');
            if (!str_starts_with($key, 'campaign:' . $campaign->id . ':')) {
                continue;
            }

            $status = (string) ($item['status'] ?? 'pending');
            if ($status === 'abandoned') {
                $result['abandoned']++;
                continue;
            }

            if ($status !== 'failed') {
                $result['skipped']++;
                continue;
            }

            $item['status'] = 'pending';
            $item['next_attempt_at'] = $now->format(DATE_ATOM);
            $item['last_error'] = null;
            $this->write($path, $item);
            $result['retried']++;
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function read(string $path): array
    {
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            throw new RuntimeException("Mail queue item [{$path}] is invalid.");
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function write(string $path, array $data): void
    {
        $this->files->write(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        );
        @chmod($path, 0600);
    }
}

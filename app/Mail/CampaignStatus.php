<?php

declare(strict_types=1);

namespace Katakata\Mail;

use DateTimeImmutable;
use RuntimeException;

final class CampaignStatus
{
    public function __construct(private readonly string $queuePath)
    {
    }

    /**
     * @return array{
     *   campaign_id: string,
     *   total: int,
     *   pending: int,
     *   delivered: int,
     *   failed: int,
     *   abandoned: int,
     *   retryable: int,
     *   progress: int,
     *   status: string,
     *   started_at: ?string,
     *   completed_at: ?string,
     *   failures: list<array{recipient: string, error: string, attempts: int, status: string}>
     * }
     */
    public function summarize(Campaign $campaign): array
    {
        $summary = [
            'campaign_id' => $campaign->id,
            'total' => $campaign->recipientCount(),
            'pending' => 0,
            'delivered' => 0,
            'failed' => 0,
            'abandoned' => 0,
            'retryable' => 0,
            'progress' => 0,
            'status' => 'queued',
            'started_at' => null,
            'completed_at' => null,
            'failures' => [],
        ];

        $created = [];
        $completed = [];

        foreach (glob(rtrim($this->queuePath, '/') . '/*.json') ?: [] as $path) {
            $item = $this->read($path);
            $key = (string) ($item['idempotency_key'] ?? '');
            if (!str_starts_with($key, 'campaign:' . $campaign->id . ':')) {
                continue;
            }

            $status = (string) ($item['status'] ?? 'pending');
            if ($status === 'delivered') {
                $summary['delivered']++;
                $deliveredAt = $item['delivered_at'] ?? null;
                if (is_string($deliveredAt) && $deliveredAt !== '') {
                    $completed[] = new DateTimeImmutable($deliveredAt);
                }
            } elseif (in_array($status, ['failed', 'abandoned'], true)) {
                $summary[$status]++;
                if ($status === 'failed') {
                    $summary['retryable']++;
                }
                $message = is_array($item['message'] ?? null) ? $item['message'] : [];
                $summary['failures'][] = [
                    'recipient' => (string) ($message['to'] ?? ''),
                    'error' => (string) ($item['last_error'] ?? 'Unknown delivery failure.'),
                    'attempts' => (int) ($item['attempts'] ?? 0),
                    'status' => $status,
                ];
            } else {
                $summary['pending']++;
            }

            $createdAt = $item['created_at'] ?? null;
            if (is_string($createdAt) && $createdAt !== '') {
                $created[] = new DateTimeImmutable($createdAt);
            }
        }

        if ($created !== []) {
            usort($created, static fn (DateTimeImmutable $a, DateTimeImmutable $b): int => $a <=> $b);
            $summary['started_at'] = $created[0]->format(DATE_ATOM);
        }

        $resolved = $summary['delivered'] + $summary['abandoned'];
        $summary['progress'] = $summary['total'] > 0
            ? (int) floor(($resolved / $summary['total']) * 100)
            : 100;

        if ($summary['total'] === 0 || $resolved >= $summary['total']) {
            if ($summary['abandoned'] === 0) {
                $summary['status'] = 'completed';
            } elseif ($summary['delivered'] === 0) {
                $summary['status'] = 'failed';
            } else {
                $summary['status'] = 'partial_failure';
            }

            if ($completed !== []) {
                usort($completed, static fn (DateTimeImmutable $a, DateTimeImmutable $b): int => $b <=> $a);
                $summary['completed_at'] = $completed[0]->format(DATE_ATOM);
            }
        } elseif ($summary['failed'] > 0 || $summary['delivered'] > 0) {
            $summary['status'] = 'sending';
        }

        return $summary;
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
}

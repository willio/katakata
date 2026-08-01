<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Mail;

use DateTimeImmutable;
use Katakata\Mail\Campaign;
use Katakata\Mail\CampaignStatus;
use PHPUnit\Framework\TestCase;

final class CampaignStatusTest extends TestCase
{
    private string $queuePath;

    protected function setUp(): void
    {
        $this->queuePath = sys_get_temp_dir() . '/katakata-campaign-status-' . bin2hex(random_bytes(6));
        mkdir($this->queuePath, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->queuePath . '/*.json') ?: [] as $path) {
            unlink($path);
        }
        rmdir($this->queuePath);
    }

    public function testItSummarizesPendingDeliveredAndFailedQueueEntries(): void
    {
        $campaign = $this->campaign();
        $this->queue('a', $campaign->id, 'pending', 'one@example.com', 0);
        $this->queue('b', $campaign->id, 'delivered', 'two@example.com', 1, null, '2026-07-31T01:05:00+00:00', '2026-07-31T01:01:00+00:00');
        $this->queue('c', $campaign->id, 'failed', 'three@example.com', 2, 'Provider unavailable.', null, '2026-07-31T01:02:00+00:00');
        $this->queue('d', str_repeat('b', 32), 'delivered', 'other@example.com', 1, null, '2026-07-31T01:03:00+00:00', '2026-07-31T01:03:00+00:00');

        $summary = (new CampaignStatus($this->queuePath))->summarize($campaign);

        self::assertSame(3, $summary['total']);
        self::assertSame(1, $summary['pending']);
        self::assertSame(1, $summary['delivered']);
        self::assertSame(1, $summary['failed']);
        self::assertSame(0, $summary['abandoned']);
        self::assertSame(1, $summary['retryable']);
        self::assertSame(33, $summary['progress']);
        self::assertSame('sending', $summary['status']);
        self::assertSame('2026-07-31T01:00:00+00:00', $summary['started_at']);
        self::assertNull($summary['completed_at']);
    }

    public function testItMarksAResolvedMixedCampaignAsPartialFailure(): void
    {
        $campaign = $this->campaign();
        $this->queue('a', $campaign->id, 'delivered', 'one@example.com', 1, null, '2026-07-31T01:04:00+00:00');
        $this->queue('b', $campaign->id, 'delivered', 'two@example.com', 1, null, '2026-07-31T01:05:00+00:00');
        $this->queue('c', $campaign->id, 'abandoned', 'three@example.com', 7, 'Rejected.');

        $summary = (new CampaignStatus($this->queuePath))->summarize($campaign);

        self::assertSame(0, $summary['failed']);
        self::assertSame(1, $summary['abandoned']);
        self::assertSame(0, $summary['retryable']);
        self::assertSame(100, $summary['progress']);
        self::assertSame('partial_failure', $summary['status']);
        self::assertSame('2026-07-31T01:05:00+00:00', $summary['completed_at']);
    }

    private function campaign(): Campaign
    {
        return new Campaign(
            id: str_repeat('a', 32),
            postSlug: 'newsletter',
            subject: 'Newsletter',
            canonicalUrl: 'https://example.test/2026/07/newsletter',
            html: '<p>Hello</p>',
            text: 'Hello',
            recipients: [
                ['email' => 'one@example.com', 'unsubscribe_token' => 'one'],
                ['email' => 'two@example.com', 'unsubscribe_token' => 'two'],
                ['email' => 'three@example.com', 'unsubscribe_token' => 'three'],
            ],
            status: 'queued',
            createdAt: new DateTimeImmutable('2026-07-31T00:59:00+00:00'),
            confirmedAt: new DateTimeImmutable('2026-07-31T00:59:30+00:00'),
        );
    }

    private function queue(
        string $suffix,
        string $campaignId,
        string $status,
        string $recipient,
        int $attempts,
        ?string $error = null,
        ?string $deliveredAt = null,
        string $createdAt = '2026-07-31T01:00:00+00:00',
    ): void {
        file_put_contents($this->queuePath . '/' . $suffix . '.json', json_encode([
            'version' => 1,
            'id' => $suffix,
            'idempotency_key' => 'campaign:' . $campaignId . ':' . hash('sha256', $recipient),
            'status' => $status,
            'attempts' => $attempts,
            'created_at' => $createdAt,
            'next_attempt_at' => $createdAt,
            'delivered_at' => $deliveredAt,
            'last_error' => $error,
            'message' => ['to' => $recipient, 'subject' => 'Newsletter', 'html' => '<p>Hello</p>', 'text' => 'Hello'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Mail;

use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;
use Katakata\Mail\Campaign;
use Katakata\Mail\CampaignRetryService;
use PHPUnit\Framework\TestCase;

final class CampaignRetryServiceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-campaign-retry-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($this->root);
    }

    public function testItRetriesOnlyFailedItemsForTheCampaign(): void
    {
        $campaign = $this->campaign(str_repeat('a', 32));
        $other = $this->campaign(str_repeat('b', 32));
        $this->write('failed.json', $campaign->id, 'failed', 3, 'Temporary failure');
        $this->write('delivered.json', $campaign->id, 'delivered', 1, null);
        $this->write('abandoned.json', $campaign->id, 'abandoned', 7, 'Terminal failure');
        $this->write('other.json', $other->id, 'failed', 2, 'Other campaign');

        $now = new DateTimeImmutable('2026-07-31T12:00:00+07:00');
        $result = (new CampaignRetryService($this->root, new AtomicFile()))->retry($campaign, $now);

        self::assertSame(['retried' => 1, 'skipped' => 1, 'abandoned' => 1], $result);

        $retried = $this->read('failed.json');
        self::assertSame('pending', $retried['status']);
        self::assertSame(3, $retried['attempts']);
        self::assertSame($now->format(DATE_ATOM), $retried['next_attempt_at']);
        self::assertNull($retried['last_error']);

        self::assertSame('abandoned', $this->read('abandoned.json')['status']);
        self::assertSame('failed', $this->read('other.json')['status']);
    }

    private function campaign(string $id): Campaign
    {
        $now = new DateTimeImmutable('2026-07-31T08:00:00+07:00');

        return new Campaign(
            id: $id,
            postSlug: 'newsletter',
            subject: 'Newsletter',
            canonicalUrl: 'https://example.test/2026/07/newsletter',
            html: '<p>Hello</p>',
            text: "Hello\n",
            recipients: [['email' => 'reader@example.com', 'unsubscribe_token' => 'token']],
            status: 'queued',
            createdAt: $now,
            confirmedAt: $now,
        );
    }

    private function write(string $file, string $campaignId, string $status, int $attempts, ?string $error): void
    {
        file_put_contents($this->root . '/' . $file, json_encode([
            'idempotency_key' => 'campaign:' . $campaignId . ':recipient',
            'status' => $status,
            'attempts' => $attempts,
            'next_attempt_at' => '2026-07-31T13:00:00+07:00',
            'last_error' => $error,
            'message' => ['to' => 'reader@example.com'],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private function read(string $file): array
    {
        $data = json_decode((string) file_get_contents($this->root . '/' . $file), true);
        self::assertIsArray($data);

        return $data;
    }
}

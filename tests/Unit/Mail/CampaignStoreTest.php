<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Mail;

use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;
use Katakata\Mail\Campaign;
use Katakata\Mail\CampaignStore;
use PHPUnit\Framework\TestCase;

final class CampaignStoreTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-campaign-store-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($this->root);
    }

    public function testItPersistsAndLoadsAnImmutableCampaignSnapshot(): void
    {
        $store = new CampaignStore($this->root, new AtomicFile());
        $now = new DateTimeImmutable('2026-07-31T08:00:00+07:00');
        $campaign = new Campaign(
            id: str_repeat('a', 32),
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

        $store->create($campaign);
        $loaded = $store->find($campaign->id);

        self::assertNotNull($loaded);
        self::assertSame($campaign->id, $loaded->id);
        self::assertSame('newsletter', $loaded->postSlug);
        self::assertSame(1, $loaded->recipientCount());
        self::assertSame('reader@example.com', $loaded->recipients[0]['email']);
        self::assertSame('queued', $loaded->status);
        self::assertSame($now->format(DATE_ATOM), $loaded->confirmedAt->format(DATE_ATOM));
    }

    public function testAllReturnsNewestConfirmedCampaignFirst(): void
    {
        $store = new CampaignStore($this->root, new AtomicFile());
        $store->create($this->campaign('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'Older', '2026-07-29T09:00:00+00:00'));
        $store->create($this->campaign('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'Newer', '2026-07-30T09:00:00+00:00'));

        self::assertSame(['Newer', 'Older'], array_map(
            static fn (Campaign $campaign): string => $campaign->subject,
            $store->all(),
        ));
    }

    public function testAllIgnoresInvalidCampaignFiles(): void
    {
        file_put_contents($this->root . '/invalid.json', '{invalid');
        $store = new CampaignStore($this->root, new AtomicFile());
        $store->create($this->campaign('cccccccccccccccccccccccccccccccc', 'Valid', '2026-07-30T09:00:00+00:00'));

        self::assertSame(['Valid'], array_map(
            static fn (Campaign $campaign): string => $campaign->subject,
            $store->all(),
        ));
    }

    private function campaign(string $id, string $subject, string $confirmedAt): Campaign
    {
        $date = new DateTimeImmutable($confirmedAt);
        return new Campaign(
            id: $id,
            postSlug: strtolower($subject),
            subject: $subject,
            canonicalUrl: 'https://example.test/' . strtolower($subject),
            html: '<p>Body</p>',
            text: 'Body',
            recipients: [['email' => 'reader@example.com', 'unsubscribe_token' => 'token']],
            status: 'queued',
            createdAt: $date,
            confirmedAt: $date,
        );
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Mail;

use DateTimeImmutable;
use Katakata\Content\Post;
use Katakata\Mail\Campaign;
use Katakata\Mail\CampaignDraftFactory;
use PHPUnit\Framework\TestCase;

final class CampaignDraftFactoryTest extends TestCase
{
    public function testFromPostCapturesImmutableSourceSnapshotWithoutChangingBytes(): void
    {
        $root = sys_get_temp_dir() . '/katakata-campaign-draft-factory-' . bin2hex(random_bytes(6));
        mkdir($root, 0775, true);
        $path = $root . '/post.md';
        $bytes = "---\ntitle: Source post\nrevision: rev-7\n---\n\nOriginal body.\n";
        file_put_contents($path, $bytes);

        $post = new Post(
            slug: 'source-post',
            title: 'Source post',
            date: new DateTimeImmutable('2026-07-31T00:00:00+00:00'),
            author: 'Will',
            tags: [],
            excerpt: 'Source excerpt',
            status: 'published',
            body: "Original body.\n",
            meta: ['revision' => 'rev-7'],
            path: $path,
        );

        $draft = (new CampaignDraftFactory())->fromPost(
            $post,
            'owner@example.com',
            new DateTimeImmutable('2026-08-01T12:00:00+00:00'),
        );

        self::assertSame($bytes, file_get_contents($path));
        self::assertSame('post', $draft->sourceType);
        self::assertSame('source-post', $draft->sourceId);
        self::assertSame('rev-7', $draft->sourceRevision);
        self::assertSame(hash('sha256', $bytes), $draft->sourceHash);
        self::assertSame('Source post', $draft->subject);
        self::assertSame("Original body.\n", $draft->body);
    }

    public function testFromCampaignCreatesDistinctDraftWithImmutableCampaignSnapshot(): void
    {
        $campaign = new Campaign(
            id: str_repeat('c', 32),
            postSlug: 'source-post',
            subject: 'Delivered subject',
            canonicalUrl: 'https://example.test/2026/07/source-post',
            html: '<p>Delivered body.</p>',
            text: "Delivered body.\n",
            recipients: [
                ['email' => 'reader@example.com', 'unsubscribe_token' => 'token'],
            ],
            status: 'queued',
            createdAt: new DateTimeImmutable('2026-08-01T09:00:00+00:00'),
            confirmedAt: new DateTimeImmutable('2026-08-01T09:05:00+00:00'),
        );

        $draft = (new CampaignDraftFactory())->fromCampaign(
            $campaign,
            'admin@example.com',
            new DateTimeImmutable('2026-08-01T12:30:00+00:00'),
        );

        self::assertNotSame($campaign->id, $draft->id);
        self::assertSame('campaign', $draft->sourceType);
        self::assertSame($campaign->id, $draft->sourceId);
        self::assertSame($campaign->confirmedAt->format(DATE_ATOM), $draft->sourceRevision);
        self::assertSame($campaign->subject, $draft->subject);
        self::assertSame($campaign->text, $draft->body);
        self::assertSame(
            hash('sha256', json_encode($campaign->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            $draft->sourceHash,
        );
        self::assertCount(1, $campaign->recipients);
    }
}

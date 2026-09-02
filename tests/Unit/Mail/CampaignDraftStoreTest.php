<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Mail;

use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;
use Katakata\Mail\CampaignDraft;
use Katakata\Mail\CampaignDraftConflict;
use Katakata\Mail\CampaignDraftStore;
use PHPUnit\Framework\TestCase;

final class CampaignDraftStoreTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/katakata-campaign-drafts-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->path . '/*.json') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->path);
    }

    public function testSavingIncrementsVersionAndPreservesImmutableProvenance(): void
    {
        $store = new CampaignDraftStore($this->path, new AtomicFile());
        $created = $store->create($this->draft());

        $saved = $store->save(new CampaignDraft(
            id: $created->id,
            subject: 'Revised subject',
            preheader: 'Revised preheader',
            body: 'Revised body',
            version: $created->version,
            createdAt: $created->createdAt,
            updatedAt: new DateTimeImmutable('2026-08-01T12:30:00+00:00'),
            createdBy: 'different-actor',
            sourceType: 'blank',
            sourceId: 'different-source',
            sourceRevision: 'different-revision',
            sourceHash: 'different-hash',
            sourceCreatedAt: null,
        ), 1);

        self::assertSame(2, $saved->version);
        self::assertSame('Revised subject', $saved->subject);
        self::assertSame('owner@example.com', $saved->createdBy);
        self::assertSame('post', $saved->sourceType);
        self::assertSame('source-post', $saved->sourceId);
        self::assertSame('revision-1', $saved->sourceRevision);
        self::assertSame(str_repeat('a', 64), $saved->sourceHash);
    }

    public function testStaleExpectedVersionRaisesConflictWithCurrentServerDraft(): void
    {
        $store = new CampaignDraftStore($this->path, new AtomicFile());
        $created = $store->create($this->draft());
        $current = $store->save(new CampaignDraft(
            id: $created->id,
            subject: 'Server subject',
            preheader: $created->preheader,
            body: $created->body,
            version: $created->version,
            createdAt: $created->createdAt,
            updatedAt: new DateTimeImmutable('2026-08-01T12:30:00+00:00'),
            createdBy: $created->createdBy,
            sourceType: $created->sourceType,
            sourceId: $created->sourceId,
            sourceRevision: $created->sourceRevision,
            sourceHash: $created->sourceHash,
            sourceCreatedAt: $created->sourceCreatedAt,
        ), 1);

        try {
            $store->save($created, 1);
            self::fail('Expected a campaign draft conflict.');
        } catch (CampaignDraftConflict $conflict) {
            self::assertSame($current->id, $conflict->current->id);
            self::assertSame(2, $conflict->current->version);
            self::assertSame('Server subject', $conflict->current->subject);
        }
    }

    public function testItReturnsNullForMalformedDraftIds(): void
    {
        $store = new CampaignDraftStore($this->path, new AtomicFile());

        self::assertNull($store->find('nonexistent'));
        self::assertNull($store->find('../escape'));
        self::assertNull($store->find(str_repeat('A', 32)));
    }

    private function draft(): CampaignDraft
    {
        $createdAt = new DateTimeImmutable('2026-08-01T12:00:00+00:00');

        return new CampaignDraft(
            id: str_repeat('1', 32),
            subject: 'Original subject',
            preheader: 'Original preheader',
            body: 'Original body',
            version: 1,
            createdAt: $createdAt,
            updatedAt: $createdAt,
            createdBy: 'owner@example.com',
            sourceType: 'post',
            sourceId: 'source-post',
            sourceRevision: 'revision-1',
            sourceHash: str_repeat('a', 64),
            sourceCreatedAt: new DateTimeImmutable('2026-07-31T09:00:00+00:00'),
        );
    }
}

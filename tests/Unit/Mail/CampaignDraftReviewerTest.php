<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Mail;

use DateTimeImmutable;
use Katakata\Distribution\SubscriberStore;
use Katakata\Editorial\AtomicFile;
use Katakata\Mail\CampaignDraft;
use Katakata\Mail\CampaignDraftReviewer;
use Katakata\Rendering\Markdown;
use PHPUnit\Framework\TestCase;

final class CampaignDraftReviewerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/katakata-campaign-review-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($this->directory);
    }

    public function testReviewResolvesCurrentAudienceInsteadOfDraftCreationAudience(): void
    {
        $store = new SubscriberStore(
            $this->directory . '/subscribers.json',
            'test-secret',
            new AtomicFile(),
        );

        $first = $store->request('first@example.com', new DateTimeImmutable('2026-08-01T10:00:00+00:00'));
        $store->confirm($first['confirmation_token'], new DateTimeImmutable('2026-08-01T10:01:00+00:00'));

        $draft = $this->draft();
        self::assertArrayNotHasKey('recipients', $draft->toArray());

        $reviewer = new CampaignDraftReviewer($store, new Markdown());
        $firstReview = $reviewer->review($draft);
        self::assertSame(1, $firstReview['recipient_count']);
        self::assertSame(['first@example.com'], array_column($firstReview['recipients'], 'email'));

        $second = $store->request('second@example.com', new DateTimeImmutable('2026-08-01T11:00:00+00:00'));
        $store->confirm($second['confirmation_token'], new DateTimeImmutable('2026-08-01T11:01:00+00:00'));

        $secondReview = $reviewer->review($draft);
        self::assertSame(2, $secondReview['recipient_count']);
        self::assertSame(
            ['first@example.com', 'second@example.com'],
            array_column($secondReview['recipients'], 'email'),
        );
    }

    private function draft(): CampaignDraft
    {
        $created = new DateTimeImmutable('2026-08-01T09:00:00+00:00');

        return new CampaignDraft(
            id: str_repeat('a', 32),
            subject: 'Subject',
            preheader: 'Preheader',
            body: 'Campaign body',
            version: 1,
            createdAt: $created,
            updatedAt: $created,
            createdBy: 'owner@example.com',
            sourceType: 'post',
            sourceId: 'source-post',
            sourceRevision: 'revision-1',
            sourceHash: hash('sha256', 'source'),
            sourceCreatedAt: $created,
        );
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Mail;

use DateTimeImmutable;
use Katakata\Content\Post;

final class CampaignDraftFactory
{
    public function fromPost(Post $post, string $actor, ?DateTimeImmutable $now = null): CampaignDraft
    {
        $now ??= new DateTimeImmutable();
        $sourceBytes = (string) file_get_contents($post->path);
        $sourceRevision = isset($post->meta['revision']) ? (string) $post->meta['revision'] : null;

        return new CampaignDraft(
            id: bin2hex(random_bytes(16)),
            subject: $post->title,
            preheader: trim((string) ($post->meta['preheader'] ?? $post->excerpt ?? '')),
            body: $post->body,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
            createdBy: $actor,
            sourceType: 'post',
            sourceId: $post->slug,
            sourceRevision: $sourceRevision,
            sourceHash: hash('sha256', $sourceBytes),
            sourceCreatedAt: $post->date,
        );
    }

    public function fromCampaign(Campaign $campaign, string $actor, ?DateTimeImmutable $now = null): CampaignDraft
    {
        $now ??= new DateTimeImmutable();
        $sourceSnapshot = json_encode($campaign->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return new CampaignDraft(
            id: bin2hex(random_bytes(16)),
            subject: $campaign->subject,
            preheader: '',
            body: $campaign->text,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
            createdBy: $actor,
            sourceType: 'campaign',
            sourceId: $campaign->id,
            sourceRevision: $campaign->confirmedAt->format(DATE_ATOM),
            sourceHash: hash('sha256', $sourceSnapshot),
            sourceCreatedAt: $campaign->confirmedAt,
        );
    }
}

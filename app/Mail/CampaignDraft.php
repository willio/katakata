<?php

declare(strict_types=1);

namespace Katakata\Mail;

use DateTimeImmutable;

final readonly class CampaignDraft
{
    public function __construct(
        public string $id,
        public string $subject,
        public string $preheader,
        public string $body,
        public int $version,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public string $createdBy,
        public string $sourceType,
        public ?string $sourceId,
        public ?string $sourceRevision,
        public ?string $sourceHash,
        public ?DateTimeImmutable $sourceCreatedAt,
        public ?string $confirmedCampaignId = null,
        public ?DateTimeImmutable $confirmedAt = null,
    ) {
    }

    public function isConfirmed(): bool
    {
        return $this->confirmedCampaignId !== null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'preheader' => $this->preheader,
            'body' => $this->body,
            'version' => $this->version,
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'updated_at' => $this->updatedAt->format(DATE_ATOM),
            'created_by' => $this->createdBy,
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'source_revision' => $this->sourceRevision,
            'source_hash' => $this->sourceHash,
            'source_created_at' => $this->sourceCreatedAt?->format(DATE_ATOM),
            'confirmed_campaign_id' => $this->confirmedCampaignId,
            'confirmed_at' => $this->confirmedAt?->format(DATE_ATOM),
        ];
    }
}

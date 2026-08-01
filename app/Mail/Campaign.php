<?php

declare(strict_types=1);

namespace Katakata\Mail;

use DateTimeImmutable;

final readonly class Campaign
{
    /** @param list<array{email: string, unsubscribe_token: string}> $recipients */
    public function __construct(
        public string $id,
        public string $postSlug,
        public string $subject,
        public string $canonicalUrl,
        public string $html,
        public string $text,
        public array $recipients,
        public string $status,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $confirmedAt,
    ) {
    }

    public function recipientCount(): int
    {
        return count($this->recipients);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'version' => 1,
            'id' => $this->id,
            'post_slug' => $this->postSlug,
            'subject' => $this->subject,
            'canonical_url' => $this->canonicalUrl,
            'html' => $this->html,
            'text' => $this->text,
            'recipients' => $this->recipients,
            'recipient_count' => $this->recipientCount(),
            'status' => $this->status,
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'confirmed_at' => $this->confirmedAt->format(DATE_ATOM),
        ];
    }
}

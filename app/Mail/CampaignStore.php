<?php

declare(strict_types=1);

namespace Katakata\Mail;

use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;
use RuntimeException;

final class CampaignStore
{
    public function __construct(
        private readonly string $path,
        private readonly AtomicFile $files,
    ) {
    }

    public function create(Campaign $campaign): Campaign
    {
        $path = $this->path($campaign->id);
        if (is_file($path)) {
            throw new RuntimeException("Campaign [{$campaign->id}] already exists.");
        }

        $this->files->write(
            $path,
            json_encode($campaign->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        );
        @chmod($path, 0600);

        return $campaign;
    }

    public function find(string $id): ?Campaign
    {
        $path = $this->path($id);
        if (!is_file($path)) {
            return null;
        }

        return $this->read($path, $id);
    }

    /** @return list<Campaign> */
    public function all(): array
    {
        $campaigns = [];
        foreach (glob(rtrim($this->path, '/') . '/*.json') ?: [] as $path) {
            $id = pathinfo($path, PATHINFO_FILENAME);
            try {
                $campaigns[] = $this->read($path, $id);
            } catch (RuntimeException) {
                continue;
            }
        }

        usort(
            $campaigns,
            static fn (Campaign $left, Campaign $right): int => $right->confirmedAt <=> $left->confirmedAt,
        );

        return $campaigns;
    }

    private function read(string $path, string $id): Campaign
    {
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            throw new RuntimeException("Campaign [{$id}] is invalid.");
        }

        return new Campaign(
            id: (string) ($data['id'] ?? ''),
            postSlug: (string) ($data['post_slug'] ?? ''),
            subject: (string) ($data['subject'] ?? ''),
            canonicalUrl: (string) ($data['canonical_url'] ?? ''),
            html: (string) ($data['html'] ?? ''),
            text: (string) ($data['text'] ?? ''),
            recipients: array_values((array) ($data['recipients'] ?? [])),
            status: (string) ($data['status'] ?? 'queued'),
            createdAt: new DateTimeImmutable((string) ($data['created_at'] ?? 'now')),
            confirmedAt: new DateTimeImmutable((string) ($data['confirmed_at'] ?? 'now')),
        );
    }

    private function path(string $id): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
            throw new RuntimeException('Campaign ID is invalid.');
        }

        return rtrim($this->path, '/') . '/' . $id . '.json';
    }
}

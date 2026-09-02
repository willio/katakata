<?php

declare(strict_types=1);

namespace Katakata\Mail;

use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;
use RuntimeException;

final class CampaignDraftStore
{
    public function __construct(
        private readonly string $path,
        private readonly AtomicFile $files,
    ) {
    }

    public function create(CampaignDraft $draft): CampaignDraft
    {
        if (!is_dir($this->path) && !mkdir($this->path, 0700, true) && !is_dir($this->path)) {
            throw new RuntimeException('Unable to create campaign draft storage.');
        }

        $target = $this->target($draft->id);
        if (is_file($target)) {
            throw new RuntimeException('Campaign draft already exists.');
        }

        $this->write($target, $draft);
        return $draft;
    }

    public function find(string $id): ?CampaignDraft
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
            return null;
        }

        $target = $this->target($id);
        if (!is_file($target)) {
            return null;
        }

        return $this->decode($target, $id);
    }

    public function save(CampaignDraft $draft, int $expectedVersion): CampaignDraft
    {
        return $this->locked($draft->id, function (CampaignDraft $current) use ($draft, $expectedVersion): CampaignDraft {
            if ($current->isConfirmed()) {
                throw new RuntimeException('Confirmed campaign drafts are immutable.');
            }
            if ($current->version !== $expectedVersion) {
                throw new CampaignDraftConflict($current);
            }

            $next = new CampaignDraft(
                id: $current->id,
                subject: $draft->subject,
                preheader: $draft->preheader,
                body: $draft->body,
                version: $current->version + 1,
                createdAt: $current->createdAt,
                updatedAt: $draft->updatedAt,
                createdBy: $current->createdBy,
                sourceType: $current->sourceType,
                sourceId: $current->sourceId,
                sourceRevision: $current->sourceRevision,
                sourceHash: $current->sourceHash,
                sourceCreatedAt: $current->sourceCreatedAt,
            );

            $this->write($this->target($next->id), $next);
            return $next;
        });
    }

    /** @return array{acquired:bool,draft:CampaignDraft} */
    public function claimConfirmation(string $id, int $expectedVersion, ?DateTimeImmutable $now = null): array
    {
        return $this->locked($id, function (CampaignDraft $current) use ($expectedVersion, $now): array {
            if ($current->isConfirmed()) {
                return ['acquired' => false, 'draft' => $current];
            }
            if ($current->version !== $expectedVersion) {
                throw new CampaignDraftConflict($current);
            }

            $now ??= new DateTimeImmutable();
            $campaignId = hash('md5', 'campaign-draft:' . $current->id);
            $claimed = new CampaignDraft(
                id: $current->id,
                subject: $current->subject,
                preheader: $current->preheader,
                body: $current->body,
                version: $current->version + 1,
                createdAt: $current->createdAt,
                updatedAt: $now,
                createdBy: $current->createdBy,
                sourceType: $current->sourceType,
                sourceId: $current->sourceId,
                sourceRevision: $current->sourceRevision,
                sourceHash: $current->sourceHash,
                sourceCreatedAt: $current->sourceCreatedAt,
                confirmedCampaignId: $campaignId,
                confirmedAt: $now,
            );

            $this->write($this->target($claimed->id), $claimed);
            return ['acquired' => true, 'draft' => $claimed];
        });
    }

    /** @return list<CampaignDraft> */
    public function recent(int $limit = 20): array
    {
        $drafts = [];
        foreach (glob(rtrim($this->path, '/') . '/*.json') ?: [] as $path) {
            $id = pathinfo($path, PATHINFO_FILENAME);
            try {
                $draft = $this->find($id);
                if ($draft !== null) {
                    $drafts[] = $draft;
                }
            } catch (RuntimeException) {
                continue;
            }
        }

        usort($drafts, static fn (CampaignDraft $a, CampaignDraft $b): int => $b->updatedAt <=> $a->updatedAt);
        return array_slice($drafts, 0, max(1, $limit));
    }

    private function target(string $id): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
            throw new RuntimeException('Campaign draft ID is invalid.');
        }

        return rtrim($this->path, '/') . '/' . $id . '.json';
    }

    private function lockTarget(string $id): string
    {
        return rtrim($this->path, '/') . '/.' . $id . '.lock';
    }

    private function decode(string $target, string $id): CampaignDraft
    {
        $data = json_decode((string) file_get_contents($target), true);
        if (!is_array($data)) {
            throw new RuntimeException('Campaign draft storage is invalid.');
        }

        return new CampaignDraft(
            id: (string) ($data['id'] ?? $id),
            subject: (string) ($data['subject'] ?? ''),
            preheader: (string) ($data['preheader'] ?? ''),
            body: (string) ($data['body'] ?? ''),
            version: max(1, (int) ($data['version'] ?? 1)),
            createdAt: new DateTimeImmutable((string) ($data['created_at'] ?? 'now')),
            updatedAt: new DateTimeImmutable((string) ($data['updated_at'] ?? 'now')),
            createdBy: (string) ($data['created_by'] ?? ''),
            sourceType: (string) ($data['source_type'] ?? 'blank'),
            sourceId: isset($data['source_id']) ? (string) $data['source_id'] : null,
            sourceRevision: isset($data['source_revision']) ? (string) $data['source_revision'] : null,
            sourceHash: isset($data['source_hash']) ? (string) $data['source_hash'] : null,
            sourceCreatedAt: isset($data['source_created_at']) && $data['source_created_at'] !== null
                ? new DateTimeImmutable((string) $data['source_created_at'])
                : null,
            confirmedCampaignId: isset($data['confirmed_campaign_id']) && $data['confirmed_campaign_id'] !== null
                ? (string) $data['confirmed_campaign_id']
                : null,
            confirmedAt: isset($data['confirmed_at']) && $data['confirmed_at'] !== null
                ? new DateTimeImmutable((string) $data['confirmed_at'])
                : null,
        );
    }

    /** @template T @param callable(CampaignDraft):T $operation @return T */
    private function locked(string $id, callable $operation): mixed
    {
        if (!is_dir($this->path) && !mkdir($this->path, 0700, true) && !is_dir($this->path)) {
            throw new RuntimeException('Unable to create campaign draft storage.');
        }

        $lock = fopen($this->lockTarget($id), 'c+');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('Unable to lock campaign draft.');
        }

        try {
            $target = $this->target($id);
            if (!is_file($target)) {
                throw new RuntimeException('Campaign draft not found.');
            }
            return $operation($this->decode($target, $id));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function write(string $target, CampaignDraft $draft): void
    {
        $this->files->write(
            $target,
            json_encode($draft->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        );
        @chmod($target, 0600);
    }
}

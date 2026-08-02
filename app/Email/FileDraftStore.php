<?php

declare(strict_types=1);

namespace Katakata\Email;

use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;
use RuntimeException;

final class FileDraftStore implements DraftStore
{
    public function __construct(
        private readonly string $path,
        private readonly AtomicFile $files,
    ) {
    }

    public function create(Draft $draft): Draft
    {
        $this->ensureDirectory();
        $target = $this->target($draft->id);
        if (is_file($target)) {
            throw new RuntimeException('Mail draft already exists.');
        }
        $this->write($target, $draft);
        return $draft;
    }

    public function save(Draft $draft, int $expectedVersion): Draft
    {
        return $this->locked($draft->id, function (Draft $current) use ($draft, $expectedVersion): Draft {
            if ($current->version !== $expectedVersion) {
                throw new DraftConflict($current);
            }

            $next = new Draft(
                id: $current->id,
                to: $draft->to,
                subject: $draft->subject,
                text: $draft->text,
                inReplyTo: $current->inReplyTo,
                version: $current->version + 1,
                createdAt: $current->createdAt,
                updatedAt: $draft->updatedAt,
            );
            $this->write($this->target($next->id), $next);
            return $next;
        });
    }

    public function find(string $id): ?Draft
    {
        $target = $this->target($id);
        return is_file($target) ? $this->decode($target, $id) : null;
    }

    public function recent(int $limit = 8): array
    {
        $drafts = [];
        foreach (glob(rtrim($this->path, '/') . '/*.json') ?: [] as $path) {
            $id = rawurldecode(pathinfo($path, PATHINFO_FILENAME));
            try {
                $draft = $this->find($id);
                if ($draft !== null) {
                    $drafts[] = $draft;
                }
            } catch (RuntimeException) {
                continue;
            }
        }
        usort($drafts, static fn (Draft $left, Draft $right): int => $right->updatedAt <=> $left->updatedAt);
        return array_slice($drafts, 0, max(1, $limit));
    }

    public function delete(string $id): void
    {
        @unlink($this->target($id));
    }

    public function deleteIfVersion(string $id, int $expectedVersion): bool
    {
        return $this->locked($id, function (Draft $current) use ($id, $expectedVersion): bool {
            if ($current->version !== $expectedVersion) {
                return false;
            }
            return @unlink($this->target($id));
        });
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->path) && !mkdir($this->path, 0700, true) && !is_dir($this->path)) {
            throw new RuntimeException('Unable to create mail draft storage.');
        }
    }

    private function target(string $id): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
            throw new RuntimeException('Mail draft ID is invalid.');
        }
        return rtrim($this->path, '/') . '/' . $id . '.json';
    }

    private function lockTarget(string $id): string
    {
        return rtrim($this->path, '/') . '/.' . $id . '.lock';
    }

    private function decode(string $target, string $id): Draft
    {
        $payload = json_decode((string) file_get_contents($target), true);
        if (!is_array($payload)) {
            throw new RuntimeException('Mail draft storage is invalid.');
        }
        $updatedAt = new DateTimeImmutable((string) ($payload['updated_at'] ?? 'now'));
        return new Draft(
            id: (string) ($payload['id'] ?? $id),
            to: (string) ($payload['to'] ?? ''),
            subject: (string) ($payload['subject'] ?? ''),
            text: (string) ($payload['text'] ?? ''),
            inReplyTo: isset($payload['in_reply_to']) ? (string) $payload['in_reply_to'] : null,
            version: max(1, (int) ($payload['version'] ?? 1)),
            createdAt: new DateTimeImmutable((string) ($payload['created_at'] ?? $updatedAt->format(DATE_ATOM))),
            updatedAt: $updatedAt,
        );
    }

    /** @template T @param callable(Draft):T $operation @return T */
    private function locked(string $id, callable $operation): mixed
    {
        $this->ensureDirectory();
        $lock = fopen($this->lockTarget($id), 'c+');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('Unable to lock mail draft.');
        }
        try {
            $target = $this->target($id);
            if (!is_file($target)) {
                throw new RuntimeException('Mail draft not found.');
            }
            return $operation($this->decode($target, $id));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function write(string $target, Draft $draft): void
    {
        $payload = [
            'id' => $draft->id,
            'to' => $draft->to,
            'subject' => $draft->subject,
            'text' => $draft->text,
            'in_reply_to' => $draft->inReplyTo,
            'version' => $draft->version,
            'created_at' => $draft->createdAt->format(DATE_ATOM),
            'updated_at' => $draft->updatedAt->format(DATE_ATOM),
        ];
        $this->files->write($target, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        @chmod($target, 0600);
    }
}

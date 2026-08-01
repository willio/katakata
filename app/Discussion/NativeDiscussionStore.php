<?php
declare(strict_types=1);

namespace Katakata\Discussion;

use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;
use RuntimeException;

final class NativeDiscussionStore
{
    private const AUTHOR_MAX_LENGTH = 120;
    private const BODY_MAX_LENGTH = 5000;
    private const AUTHOR_COOLDOWN_SECONDS = 5;

    public function __construct(
        private readonly string $path,
        private readonly AtomicFile $files,
    ) {
    }

    public function create(string $threadId, string $postSlug, ?DateTimeImmutable $now = null): DiscussionReference
    {
        $threadId = $this->validateId($threadId);
        $this->withThreadLock($threadId, function (string $path) use ($threadId, $postSlug, $now): void {
            if (is_file($path)) {
                return;
            }

            $this->write($path, [
                'version' => 1,
                'id' => $threadId,
                'post_slug' => $postSlug,
                'created_at' => ($now ?? new DateTimeImmutable())->format(DATE_ATOM),
                'entries' => [],
            ]);
        });

        return new DiscussionReference('native', $threadId, '/discussion/' . rawurlencode($threadId));
    }

    public function fetch(DiscussionReference $reference): DiscussionThread
    {
        if ($reference->provider !== 'native') {
            throw new RuntimeException('Discussion reference does not belong to the native provider.');
        }

        $data = $this->read($this->threadPath($this->validateId($reference->id)));
        $entries = [];
        foreach ((array) ($data['entries'] ?? []) as $entry) {
            if (is_array($entry) && ($entry['status'] ?? null) === 'approved') {
                $entries[] = $this->entry($entry);
            }
        }

        usort($entries, static fn (DiscussionEntry $a, DiscussionEntry $b): int => $a->publishedAt <=> $b->publishedAt);
        return new DiscussionThread($reference, $entries);
    }

    public function submit(
        DiscussionReference $reference,
        string $authorName,
        string $body,
        ?string $parentId = null,
        array $spam = [],
        ?DateTimeImmutable $now = null,
    ): DiscussionEntry {
        if ($reference->provider !== 'native') {
            throw new RuntimeException('Discussion reference does not belong to the native provider.');
        }

        $authorName = trim($authorName);
        $body = trim($body);
        if ($authorName === '' || $body === '') {
            throw new RuntimeException('Author name and comment body are required.');
        }
        if (mb_strlen($authorName, 'UTF-8') > self::AUTHOR_MAX_LENGTH) {
            throw new RuntimeException('Author name must not exceed 120 characters.');
        }
        if (mb_strlen($body, 'UTF-8') > self::BODY_MAX_LENGTH) {
            throw new RuntimeException('Comment body must not exceed 5000 characters.');
        }
        if ($this->honeypotIsFilled($spam)) {
            throw new RuntimeException('Spam check failed.');
        }

        $threadId = $this->validateId($reference->id);
        $submittedAt = $now ?? new DateTimeImmutable();
        return $this->withThreadLock($threadId, function (string $path) use ($authorName, $body, $parentId, $spam, $submittedAt): DiscussionEntry {
            $data = $this->read($path);
            $this->assertAuthorCooldown($data, $authorName, $submittedAt);
            $record = [
                'id' => bin2hex(random_bytes(16)),
                'author_name' => $authorName,
                'body' => $body,
                'published_at' => $submittedAt->format(DATE_ATOM),
                'parent_id' => $parentId,
                'status' => 'pending',
                'spam' => $spam,
            ];
            $data['entries'][] = $record;
            $this->write($path, $data);

            return $this->entry($record);
        });
    }

    public function moderate(
        DiscussionReference $reference,
        string $entryId,
        string $status,
        ?string $moderatedBy = null,
        ?DateTimeImmutable $now = null,
    ): void {
        if (!in_array($status, ['approved', 'rejected', 'spam'], true)) {
            throw new RuntimeException('Moderation status is invalid.');
        }

        $threadId = $this->validateId($reference->id);
        $this->withThreadLock($threadId, function (string $path) use ($entryId, $status, $moderatedBy, $now): void {
            $data = $this->read($path);
            foreach ($data['entries'] as &$entry) {
                if (is_array($entry) && ($entry['id'] ?? null) === $entryId) {
                    $entry['status'] = $status;
                    $entry['moderated_at'] = ($now ?? new DateTimeImmutable())->format(DATE_ATOM);
                    $entry['moderated_by'] = $moderatedBy === null || trim($moderatedBy) === '' ? null : trim($moderatedBy);
                    $this->write($path, $data);
                    return;
                }
            }
            unset($entry);

            throw new RuntimeException("Discussion entry [{$entryId}] was not found.");
        });
    }

    /** @return list<DiscussionEntry> */
    public function recentApproved(int $limit = 8): array
    {
        $entries = [];
        foreach (glob(rtrim($this->path, '/') . '/*.json') ?: [] as $path) {
            try {
                $data = $this->read($path);
            } catch (RuntimeException) {
                continue;
            }
            foreach ((array) $data['entries'] as $entry) {
                if (is_array($entry) && ($entry['status'] ?? null) === 'approved') {
                    $entries[] = $this->entry($entry);
                }
            }
        }

        usort($entries, static fn (DiscussionEntry $a, DiscussionEntry $b): int => $b->publishedAt <=> $a->publishedAt);
        return array_slice($entries, 0, max(0, $limit));
    }

    private function entry(array $record): DiscussionEntry
    {
        return new DiscussionEntry(
            id: (string) ($record['id'] ?? ''),
            authorName: (string) ($record['author_name'] ?? ''),
            body: (string) ($record['body'] ?? ''),
            publishedAt: new DateTimeImmutable((string) ($record['published_at'] ?? 'now')),
            parentId: is_string($record['parent_id'] ?? null) ? $record['parent_id'] : null,
            metadata: [
                'moderation_status' => (string) ($record['status'] ?? 'pending'),
                'moderated_at' => is_string($record['moderated_at'] ?? null) ? $record['moderated_at'] : null,
                'moderated_by' => is_string($record['moderated_by'] ?? null) ? $record['moderated_by'] : null,
                'spam' => is_array($record['spam'] ?? null) ? $record['spam'] : [],
            ],
        );
    }

    private function validateId(string $id): string
    {
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,127}$/', $id)) {
            throw new RuntimeException('Native discussion ID is invalid.');
        }
        return $id;
    }

    private function threadPath(string $id): string
    {
        return rtrim($this->path, '/') . '/' . $id . '.json';
    }

    private function lockPath(string $id): string
    {
        return rtrim($this->path, '/') . '/' . $id . '.lock';
    }

    /** @param array<string, mixed> $spam */
    private function honeypotIsFilled(array $spam): bool
    {
        $value = $spam['honeypot'] ?? null;
        return $value !== null && trim(is_scalar($value) ? (string) $value : 'filled') !== '';
    }

    /** @param array<string, mixed> $data */
    private function assertAuthorCooldown(array $data, string $authorName, DateTimeImmutable $submittedAt): void
    {
        foreach (array_reverse((array) ($data['entries'] ?? [])) as $entry) {
            if (!is_array($entry) || ($entry['author_name'] ?? null) !== $authorName) {
                continue;
            }

            try {
                $previous = new DateTimeImmutable((string) ($entry['published_at'] ?? ''));
            } catch (\Throwable) {
                continue;
            }

            if (($submittedAt->getTimestamp() - $previous->getTimestamp()) < self::AUTHOR_COOLDOWN_SECONDS) {
                throw new RuntimeException('Please wait before submitting another comment.');
            }
            return;
        }
    }

    private function withThreadLock(string $threadId, callable $callback): mixed
    {
        $directory = rtrim($this->path, '/');
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create native discussion directory [{$directory}].");
        }

        $lock = fopen($this->lockPath($threadId), 'c');
        if ($lock === false) {
            throw new RuntimeException("Unable to open native discussion lock [{$threadId}].");
        }
        @chmod($this->lockPath($threadId), 0600);

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException("Unable to acquire native discussion lock [{$threadId}].");
            }

            return $callback($this->threadPath($threadId));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array<string, mixed> */
    private function read(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('Native discussion thread was not found.');
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data) || !is_array($data['entries'] ?? null)) {
            throw new RuntimeException("Native discussion thread [{$path}] is invalid.");
        }
        return $data;
    }

    /** @param array<string, mixed> $data */
    private function write(string $path, array $data): void
    {
        $this->files->write($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        @chmod($path, 0600);
    }
}

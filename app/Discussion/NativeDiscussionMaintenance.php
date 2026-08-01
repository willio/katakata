<?php
declare(strict_types=1);

namespace Katakata\Discussion;

use DateInterval;
use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;
use RuntimeException;

final class NativeDiscussionMaintenance
{
    private readonly AtomicFile $files;

    public function __construct(private readonly string $path, ?AtomicFile $files = null)
    {
        $this->files = $files ?? new AtomicFile();
    }

    /** @return array{entries: int, threads_removed: int} */
    public function prune(int $days, ?DateTimeImmutable $now = null): array
    {
        if ($days < 1) {
            throw new RuntimeException('Discussion retention must be at least one day.');
        }

        $cutoff = ($now ?? new DateTimeImmutable())->sub(new DateInterval('P' . $days . 'D'));
        $deleted = 0;
        $threadsRemoved = 0;
        foreach (glob(rtrim($this->path, '/') . '/*.json') ?: [] as $path) {
            $threadId = pathinfo($path, PATHINFO_FILENAME);
            $removed = $this->withThreadLock($threadId, function (string $lockedPath) use ($cutoff, &$deleted): bool {
                if (!is_file($lockedPath)) {
                    return false;
                }

                $data = $this->read($lockedPath);
                $entries = array_values(array_filter((array) $data['entries'], static function (mixed $entry) use ($cutoff, &$deleted): bool {
                    if (!is_array($entry) || !in_array((string) ($entry['status'] ?? 'pending'), ['rejected', 'spam'], true)) {
                        return true;
                    }
                    try {
                        $moderatedAt = new DateTimeImmutable((string) ($entry['moderated_at'] ?? $entry['published_at'] ?? 'now'));
                    } catch (\Throwable) {
                        return true;
                    }
                    if ($moderatedAt >= $cutoff) {
                        return true;
                    }
                    $deleted++;
                    return false;
                }));
                if ($entries === []) {
                    if (!unlink($lockedPath)) {
                        throw new RuntimeException("Unable to remove empty discussion thread [{$lockedPath}].");
                    }
                    return true;
                }

                $data['entries'] = $entries;
                $this->write($lockedPath, $data);
                return false;
            });
            if ($removed) {
                $threadsRemoved++;
            }
        }

        return ['entries' => $deleted, 'threads_removed' => $threadsRemoved];
    }

    /** @return array<string, mixed> */
    private function read(string $path): array
    {
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

    private function withThreadLock(string $threadId, callable $callback): mixed
    {
        $lockPath = rtrim($this->path, '/') . '/' . $threadId . '.lock';
        $lock = fopen($lockPath, 'c');
        if ($lock === false) {
            throw new RuntimeException("Unable to open native discussion lock [{$threadId}].");
        }
        @chmod($lockPath, 0600);

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException("Unable to acquire native discussion lock [{$threadId}].");
            }

            return $callback(rtrim($this->path, '/') . '/' . $threadId . '.json');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}

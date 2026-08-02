<?php

declare(strict_types=1);

namespace Katakata\Email;

use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;
use RuntimeException;

final class ImapSynchronizer
{
    private const RETENTION_DAYS = 30;

    public function __construct(
        private readonly ImapSettings $settings,
        private readonly ImapMailboxSource $source,
        private readonly string $cachePath,
        private readonly AtomicFile $files,
    ) {
    }

    /** @return array{fetched:int,written:int,last_synced_at:string} */
    public function sync(int $limit = 100, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();
        if (!$this->settings->configured()) {
            $this->writeIndex($this->existingIds(), 'needs_setup', 'IMAP deployment variables are incomplete.', null);
            throw new RuntimeException('IMAP deployment variables are incomplete.');
        }

        try {
            $messages = $this->source->fetch($this->settings, max(1, $limit));
            $cutoff = $now->modify('-' . self::RETENTION_DAYS . ' days');
            $written = 0;

            foreach ($messages as $message) {
                $id = $this->safe((string) ($message['id'] ?? ''));
                $receivedAt = $this->date((string) ($message['received_at'] ?? ''), $now);
                if ($receivedAt < $cutoff) {
                    continue;
                }

                $payload = [
                    'id' => $id,
                    'from' => (string) ($message['from'] ?? ''),
                    'to' => (string) ($message['to'] ?? ''),
                    'subject' => (string) ($message['subject'] ?? ''),
                    'text' => (string) ($message['text'] ?? ''),
                    'html' => isset($message['html']) ? (string) $message['html'] : null,
                    'received_at' => $receivedAt->format(DATE_ATOM),
                ];
                $target = $this->messagePath($id);
                $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
                if (!is_file($target) || file_get_contents($target) !== $encoded) {
                    $this->files->write($target, $encoded);
                    @chmod($target, 0600);
                    $written++;
                }
            }

            $ids = $this->pruneAndOrder($cutoff);
            $this->removeTree($this->cachePath . '/attachments');
            $this->pruneState($ids);
            $this->writeIndex($ids, 'ready', null, $now->format(DATE_ATOM));

            return [
                'fetched' => count($messages),
                'written' => $written,
                'last_synced_at' => $now->format(DATE_ATOM),
            ];
        } catch (\Throwable $error) {
            $this->writeIndex($this->existingIds(), 'error', $error->getMessage(), null);
            throw $error;
        }
    }

    /** @return list<string> */
    private function pruneAndOrder(DateTimeImmutable $cutoff): array
    {
        $messages = [];
        foreach (glob($this->cachePath . '/messages/*.json') ?: [] as $path) {
            $data = json_decode((string) file_get_contents($path), true);
            if (!is_array($data)) {
                @unlink($path);
                continue;
            }

            $id = $this->safe((string) ($data['id'] ?? basename($path, '.json')));
            try {
                $receivedAt = new DateTimeImmutable((string) ($data['received_at'] ?? ''));
            } catch (\Throwable) {
                @unlink($path);
                continue;
            }

            if ($receivedAt < $cutoff) {
                @unlink($path);
                continue;
            }
            $messages[$id] = $receivedAt;
        }

        uasort($messages, static fn (DateTimeImmutable $left, DateTimeImmutable $right): int => $right <=> $left);
        return array_keys($messages);
    }

    /** @param list<string> $validIds */
    private function pruneState(array $validIds): void
    {
        $path = $this->cachePath . '/state.json';
        if (!is_file($path)) {
            return;
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            @unlink($path);
            return;
        }
        $valid = array_fill_keys($validIds, true);
        $state = [
            'read' => array_values(array_filter(
                array_map('strval', (array) ($data['read'] ?? [])),
                static fn (string $id): bool => isset($valid[$id]),
            )),
            'archived' => array_values(array_filter(
                array_map('strval', (array) ($data['archived'] ?? [])),
                static fn (string $id): bool => isset($valid[$id]),
            )),
        ];
        $this->files->write($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        @chmod($path, 0600);
    }

    /** @param list<string> $ids */
    private function writeIndex(array $ids, string $state, ?string $error, ?string $syncedAt): void
    {
        $current = $this->readIndex();
        $payload = [
            'messages' => $ids,
            'status' => [
                'state' => $state,
                'error' => $error,
                'last_synced_at' => $syncedAt ?? ($current['status']['last_synced_at'] ?? null),
            ],
        ];
        $target = $this->cachePath . '/index.json';
        $this->files->write($target, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        @chmod($target, 0600);
    }

    /** @return list<string> */
    private function existingIds(): array
    {
        return array_values(array_filter(
            array_map('strval', (array) ($this->readIndex()['messages'] ?? [])),
            fn (string $id): bool => is_file($this->messagePath($id)),
        ));
    }

    /** @return array<string,mixed> */
    private function readIndex(): array
    {
        $path = $this->cachePath . '/index.json';
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    private function messagePath(string $id): string
    {
        return $this->cachePath . '/messages/' . $this->safe($id) . '.json';
    }

    private function date(string $value, DateTimeImmutable $fallback): DateTimeImmutable
    {
        if ($value === '') {
            return $fallback;
        }
        try {
            return new DateTimeImmutable($value);
        } catch (\Throwable) {
            throw new RuntimeException('IMAP message date is invalid.');
        }
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $target = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($target) ? $this->removeTree($target) : @unlink($target);
        }
        @rmdir($path);
    }

    private function safe(string $value): string
    {
        if ($value === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $value)) {
            throw new RuntimeException('IMAP message identifier is invalid.');
        }
        return $value;
    }
}

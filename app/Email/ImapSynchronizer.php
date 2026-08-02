<?php

declare(strict_types=1);

namespace Katakata\Email;

use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;
use RuntimeException;

final class ImapSynchronizer
{
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
            $this->writeIndex([], 'needs_setup', 'IMAP deployment variables are incomplete.', null);
            throw new RuntimeException('IMAP deployment variables are incomplete.');
        }

        try {
            $messages = $this->source->fetch($this->settings, max(1, $limit));
            $ids = [];
            $written = 0;
            foreach ($messages as $message) {
                $id = $this->safe((string) ($message['id'] ?? ''));
                $ids[] = $id;
                $target = $this->cachePath . '/messages/' . $id . '.json';
                $attachments = [];
                foreach ((array) ($message['attachments'] ?? []) as $attachment) {
                    if (!is_array($attachment)) {
                        continue;
                    }
                    $attachmentId = $this->safe((string) ($attachment['id'] ?? ''));
                    $content = (string) ($attachment['content'] ?? '');
                    $attachmentTarget = $this->cachePath . '/attachments/' . $id . '/' . $attachmentId;
                    $this->files->write($attachmentTarget, $content);
                    @chmod($attachmentTarget, 0600);
                    $attachments[] = [
                        'id' => $attachmentId,
                        'name' => (string) ($attachment['name'] ?? $attachmentId),
                        'media_type' => (string) ($attachment['media_type'] ?? 'application/octet-stream'),
                        'bytes' => strlen($content),
                    ];
                }

                $payload = [
                    'id' => $id,
                    'from' => (string) ($message['from'] ?? ''),
                    'to' => (string) ($message['to'] ?? ''),
                    'subject' => (string) ($message['subject'] ?? ''),
                    'text' => (string) ($message['text'] ?? ''),
                    'html' => isset($message['html']) ? (string) $message['html'] : null,
                    'received_at' => (string) ($message['received_at'] ?? $now->format(DATE_ATOM)),
                    'attachments' => $attachments,
                ];
                $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
                if (!is_file($target) || file_get_contents($target) !== $encoded) {
                    $this->files->write($target, $encoded);
                    @chmod($target, 0600);
                    $written++;
                }
            }

            $ids = array_values(array_unique($ids));
            $this->writeIndex($ids, 'ready', null, $now->format(DATE_ATOM));
            return ['fetched' => count($messages), 'written' => $written, 'last_synced_at' => $now->format(DATE_ATOM)];
        } catch (\Throwable $error) {
            $existing = $this->existingIds();
            $this->writeIndex($existing, 'error', $error->getMessage(), null);
            throw $error;
        }
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
        return array_values(array_map('strval', (array) ($this->readIndex()['messages'] ?? [])));
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

    private function safe(string $value): string
    {
        if ($value === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $value)) {
            throw new RuntimeException('IMAP message identifier is invalid.');
        }
        return $value;
    }
}

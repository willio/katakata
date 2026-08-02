<?php

declare(strict_types=1);

namespace Katakata\Email\Providers;

use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;
use Katakata\Email\ArchivedMailboxProvider;
use Katakata\Email\AttachmentDownload;
use Katakata\Email\Message;
use Katakata\Email\MessageSummary;
use RuntimeException;

final class CachedMailboxProvider implements ArchivedMailboxProvider
{
    public function __construct(
        private readonly string $path,
        private readonly AtomicFile $files,
    ) {
    }

    public function inbox(int $limit = 50): array
    {
        return $this->summaries(false, $limit);
    }

    public function archived(int $limit = 50): array
    {
        return $this->summaries(true, $limit);
    }

    public function unreadCount(): int
    {
        return count(array_filter($this->inbox(1000), static fn (MessageSummary $message): bool => $message->unread));
    }

    public function message(string $id): ?Message
    {
        $id = $this->safe($id);
        $state = $this->state();
        if (array_key_exists($id, $state['deleted'])) {
            return null;
        }

        $path = $this->messagePath($id);
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            throw new RuntimeException('Cached mailbox message is invalid.');
        }

        return new Message(
            id: (string) ($data['id'] ?? $id),
            from: (string) ($data['from'] ?? ''),
            to: (string) ($data['to'] ?? ''),
            subject: (string) ($data['subject'] ?? ''),
            text: (string) ($data['text'] ?? ''),
            html: isset($data['html']) ? (string) $data['html'] : null,
            receivedAt: new DateTimeImmutable((string) ($data['received_at'] ?? 'now')),
            unread: !in_array($id, $state['read'], true),
            attachments: [],
        );
    }

    public function attachment(string $messageId, string $attachmentId): ?AttachmentDownload
    {
        return null;
    }

    public function markRead(string $id, bool $read): void
    {
        $id = $this->safe($id);
        $state = $this->state();
        $state['read'] = array_values(array_filter($state['read'], static fn (string $value): bool => $value !== $id));
        if ($read && !array_key_exists($id, $state['deleted'])) {
            $state['read'][] = $id;
        }
        $this->writeState($state);
    }

    public function archive(string $id): void
    {
        $id = $this->safe($id);
        $state = $this->state();
        if (!in_array($id, $state['archived'], true) && !array_key_exists($id, $state['deleted'])) {
            $state['archived'][] = $id;
        }
        $this->writeState($state);
    }

    public function deleteLocal(string $id): void
    {
        $id = $this->safe($id);
        @unlink($this->messagePath($id));

        $index = $this->index();
        $index['messages'] = array_values(array_filter(
            $index['messages'],
            static fn (string $value): bool => $value !== $id,
        ));
        $this->writeIndex($index);

        $state = $this->state();
        $state['read'] = array_values(array_filter($state['read'], static fn (string $value): bool => $value !== $id));
        $state['archived'] = array_values(array_filter($state['archived'], static fn (string $value): bool => $value !== $id));
        $state['deleted'][$id] = (new DateTimeImmutable())->format(DATE_ATOM);
        $this->writeState($state);
    }

    public function readiness(): array
    {
        $status = $this->index()['status'];
        return [
            'status' => (string) ($status['state'] ?? 'needs_setup'),
            'reason' => isset($status['error']) ? (string) $status['error'] : null,
            'last_synced_at' => isset($status['last_synced_at']) ? (string) $status['last_synced_at'] : null,
        ];
    }

    /** @return list<MessageSummary> */
    private function summaries(bool $archived, int $limit): array
    {
        $messages = [];
        foreach ($this->index()['messages'] as $id) {
            $message = $this->message((string) $id);
            if ($message !== null && $this->isArchived($message->id) === $archived) {
                $messages[] = $message->summary();
            }
        }
        usort($messages, static fn (MessageSummary $a, MessageSummary $b): int => $b->receivedAt <=> $a->receivedAt);
        return array_slice($messages, 0, max(1, $limit));
    }

    /** @return array{messages:list<string>,status:array<string,mixed>} */
    private function index(): array
    {
        $path = $this->path . '/index.json';
        if (!is_file($path)) {
            return [
                'messages' => [],
                'status' => [
                    'state' => 'needs_setup',
                    'error' => 'Run the scheduled IMAP synchronizer to create the private mailbox cache.',
                ],
            ];
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            throw new RuntimeException('Cached mailbox index is invalid.');
        }
        return [
            'messages' => array_values(array_map('strval', (array) ($data['messages'] ?? []))),
            'status' => is_array($data['status'] ?? null) ? $data['status'] : [],
        ];
    }

    /** @param array{messages:list<string>,status:array<string,mixed>} $index */
    private function writeIndex(array $index): void
    {
        $target = $this->path . '/index.json';
        $this->files->write(
            $target,
            json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        );
        @chmod($target, 0600);
    }

    /** @return array{read:list<string>,archived:list<string>,deleted:array<string,string>} */
    private function state(): array
    {
        $path = $this->path . '/state.json';
        if (!is_file($path)) {
            return ['read' => [], 'archived' => [], 'deleted' => []];
        }
        $data = json_decode((string) file_get_contents($path), true);
        $deleted = [];
        foreach ((array) ($data['deleted'] ?? []) as $id => $deletedAt) {
            if (is_int($id)) {
                $deleted[(string) $deletedAt] = (new DateTimeImmutable())->format(DATE_ATOM);
                continue;
            }
            $deleted[(string) $id] = (string) $deletedAt;
        }
        return [
            'read' => array_values(array_map('strval', (array) ($data['read'] ?? []))),
            'archived' => array_values(array_map('strval', (array) ($data['archived'] ?? []))),
            'deleted' => $deleted,
        ];
    }

    /** @param array{read:list<string>,archived:list<string>,deleted:array<string,string>} $state */
    private function writeState(array $state): void
    {
        $target = $this->path . '/state.json';
        $this->files->write(
            $target,
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        );
        @chmod($target, 0600);
    }

    private function isArchived(string $id): bool
    {
        return in_array($id, $this->state()['archived'], true);
    }

    private function messagePath(string $id): string
    {
        return $this->path . '/messages/' . $this->safe($id) . '.json';
    }

    private function safe(string $value): string
    {
        if ($value === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $value)) {
            throw new RuntimeException('Cached mailbox identifier is invalid.');
        }
        return $value;
    }
}

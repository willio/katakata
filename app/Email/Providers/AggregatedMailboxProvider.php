<?php

declare(strict_types=1);

namespace Katakata\Email\Providers;

use Katakata\Email\ArchivedMailboxProvider;
use Katakata\Email\AttachmentDownload;
use Katakata\Email\MailboxProvider;
use Katakata\Email\Message;
use Katakata\Email\MessageSummary;

final class AggregatedMailboxProvider implements ArchivedMailboxProvider
{
    /** @param array<string,MailboxProvider> $providers */
    public function __construct(private readonly array $providers)
    {
    }

    public function inbox(int $limit = 50): array
    {
        return $this->merge(false, $limit);
    }

    public function archived(int $limit = 50): array
    {
        return $this->merge(true, $limit);
    }

    public function unreadCount(): int
    {
        return array_sum(array_map(static fn (MailboxProvider $provider): int => $provider->unreadCount(), $this->providers));
    }

    public function message(string $id): ?Message
    {
        $provider = $this->providerFor($id);
        return $provider?->message($id);
    }

    public function attachment(string $messageId, string $attachmentId): ?AttachmentDownload
    {
        return null;
    }

    public function markRead(string $id, bool $read): void
    {
        $this->providerFor($id)?->markRead($id, $read);
    }

    public function archive(string $id): void
    {
        $this->providerFor($id)?->archive($id);
    }

    public function deleteLocal(string $id): void
    {
        $this->providerFor($id)?->deleteLocal($id);
    }

    public function readiness(): array
    {
        if ($this->providers === []) {
            return ['status' => 'disabled', 'reason' => 'No mailbox account is enabled.', 'last_synced_at' => null];
        }

        $states = array_map(static fn (MailboxProvider $provider): array => $provider->readiness(), $this->providers);
        $ready = count(array_filter($states, static fn (array $state): bool => ($state['status'] ?? '') === 'ready'));
        $latest = null;
        foreach ($states as $state) {
            $value = $state['last_synced_at'] ?? null;
            if (is_string($value) && ($latest === null || $value > $latest)) {
                $latest = $value;
            }
        }

        if ($ready === count($states)) {
            return ['status' => 'ready', 'reason' => null, 'last_synced_at' => $latest, 'accounts' => $states];
        }
        if ($ready > 0) {
            return [
                'status' => 'partial',
                'reason' => 'Some mailbox accounts need attention.',
                'last_synced_at' => $latest,
                'accounts' => $states,
            ];
        }
        return [
            'status' => 'needs_setup',
            'reason' => 'No enabled mailbox account is currently usable.',
            'last_synced_at' => $latest,
            'accounts' => $states,
        ];
    }

    /** @return list<MessageSummary> */
    private function merge(bool $archived, int $limit): array
    {
        $messages = [];
        foreach ($this->providers as $provider) {
            $source = $archived && $provider instanceof ArchivedMailboxProvider
                ? $provider->archived($limit)
                : ($archived ? [] : $provider->inbox($limit));
            array_push($messages, ...$source);
        }
        usort($messages, static fn (MessageSummary $left, MessageSummary $right): int => $right->receivedAt <=> $left->receivedAt);
        return array_slice($messages, 0, max(1, $limit));
    }

    private function providerFor(string $id): ?MailboxProvider
    {
        $separator = strpos($id, ':');
        if ($separator === false) {
            return null;
        }
        return $this->providers[substr($id, 0, $separator)] ?? null;
    }
}

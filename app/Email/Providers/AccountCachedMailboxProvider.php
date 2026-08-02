<?php

declare(strict_types=1);

namespace Katakata\Email\Providers;

use Katakata\Email\ArchivedMailboxProvider;
use Katakata\Email\AttachmentDownload;
use Katakata\Email\MailboxAccount;
use Katakata\Email\Message;
use Katakata\Email\MessageSummary;

final class AccountCachedMailboxProvider implements ArchivedMailboxProvider
{
    public function __construct(
        private readonly MailboxAccount $account,
        private readonly CachedMailboxProvider $provider,
    ) {
    }

    public function inbox(int $limit = 50): array
    {
        return array_map(fn (MessageSummary $message): MessageSummary => $this->summary($message), $this->provider->inbox($limit));
    }

    public function archived(int $limit = 50): array
    {
        return array_map(fn (MessageSummary $message): MessageSummary => $this->summary($message), $this->provider->archived($limit));
    }

    public function unreadCount(): int
    {
        return $this->provider->unreadCount();
    }

    public function message(string $id): ?Message
    {
        $localId = $this->localId($id);
        if ($localId === null) {
            return null;
        }
        $message = $this->provider->message($localId);
        if ($message === null) {
            return null;
        }
        return new Message(
            id: $this->qualified($localId),
            from: $message->from,
            to: $message->to,
            subject: $message->subject,
            text: $message->text,
            html: null,
            receivedAt: $message->receivedAt,
            unread: $message->unread,
            attachments: [],
            sourceAccountId: $this->account->id,
            sourceLabel: $this->account->label,
            sourceMessageId: $localId,
        );
    }

    public function attachment(string $messageId, string $attachmentId): ?AttachmentDownload
    {
        return null;
    }

    public function markRead(string $id, bool $read): void
    {
        $localId = $this->localId($id);
        if ($localId !== null) {
            $this->provider->markRead($localId, $read);
        }
    }

    public function archive(string $id): void
    {
        $localId = $this->localId($id);
        if ($localId !== null) {
            $this->provider->archive($localId);
        }
    }

    public function deleteLocal(string $id): void
    {
        $localId = $this->localId($id);
        if ($localId !== null) {
            $this->provider->deleteLocal($localId);
        }
    }

    public function readiness(): array
    {
        return $this->provider->readiness() + [
            'account_id' => $this->account->id,
            'label' => $this->account->label,
        ];
    }

    private function summary(MessageSummary $message): MessageSummary
    {
        return new MessageSummary(
            id: $this->qualified($message->id),
            from: $message->from,
            subject: $message->subject,
            receivedAt: $message->receivedAt,
            unread: $message->unread,
            sourceAccountId: $this->account->id,
            sourceLabel: $this->account->label,
            sourceMessageId: $message->id,
        );
    }

    private function qualified(string $id): string
    {
        return $this->account->id . ':' . $id;
    }

    private function localId(string $id): ?string
    {
        $prefix = $this->account->id . ':';
        return str_starts_with($id, $prefix) ? substr($id, strlen($prefix)) : null;
    }
}

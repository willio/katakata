<?php

declare(strict_types=1);

namespace Katakata\Email;

use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;
use RuntimeException;

final class SentMessageStore
{
    public function __construct(
        private readonly string $path,
        private readonly AtomicFile $files,
    ) {
    }

    public function record(Draft $draft, ?DateTimeImmutable $sentAt = null): SentMessage
    {
        $sentAt ??= new DateTimeImmutable();
        $message = new SentMessage(
            id: $draft->id,
            to: $draft->to,
            subject: $draft->subject,
            text: $draft->text,
            inReplyTo: $draft->inReplyTo,
            sentAt: $sentAt,
        );
        $target = $this->path . '/' . $this->safe($message->id) . '.json';
        $this->files->write($target, json_encode([
            'id' => $message->id,
            'to' => $message->to,
            'subject' => $message->subject,
            'text' => $message->text,
            'in_reply_to' => $message->inReplyTo,
            'sent_at' => $message->sentAt->format(DATE_ATOM),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        @chmod($target, 0600);
        return $message;
    }

    /** @return list<SentMessage> */
    public function recent(int $limit = 50): array
    {
        if (!is_dir($this->path)) {
            return [];
        }
        $messages = [];
        foreach (glob($this->path . '/*.json') ?: [] as $path) {
            $data = json_decode((string) file_get_contents($path), true);
            if (!is_array($data)) {
                continue;
            }
            $messages[] = new SentMessage(
                id: (string) ($data['id'] ?? basename($path, '.json')),
                to: (string) ($data['to'] ?? ''),
                subject: (string) ($data['subject'] ?? ''),
                text: (string) ($data['text'] ?? ''),
                inReplyTo: isset($data['in_reply_to']) ? (string) $data['in_reply_to'] : null,
                sentAt: new DateTimeImmutable((string) ($data['sent_at'] ?? 'now')),
            );
        }
        usort($messages, static fn (SentMessage $a, SentMessage $b): int => $b->sentAt <=> $a->sentAt);
        return array_slice($messages, 0, max(1, $limit));
    }

    private function safe(string $value): string
    {
        if ($value === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $value)) {
            throw new RuntimeException('Sent message identifier is invalid.');
        }
        return $value;
    }
}

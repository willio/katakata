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

    public function save(Draft $draft): void
    {
        if (!is_dir($this->path) && !mkdir($this->path, 0700, true) && !is_dir($this->path)) {
            throw new RuntimeException('Unable to create mail draft storage.');
        }

        $payload = [
            'id' => $draft->id,
            'to' => $draft->to,
            'subject' => $draft->subject,
            'text' => $draft->text,
            'in_reply_to' => $draft->inReplyTo,
            'updated_at' => $draft->updatedAt->format(DATE_ATOM),
        ];

        $target = $this->path . '/' . rawurlencode($draft->id) . '.json';
        $this->files->write($target, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        @chmod($target, 0600);
    }

    public function find(string $id): ?Draft
    {
        $target = $this->path . '/' . rawurlencode($id) . '.json';
        if (!is_file($target)) {
            return null;
        }

        $payload = json_decode((string) file_get_contents($target), true);
        if (!is_array($payload)) {
            throw new RuntimeException('Mail draft storage is invalid.');
        }

        return new Draft(
            id: (string) ($payload['id'] ?? $id),
            to: (string) ($payload['to'] ?? ''),
            subject: (string) ($payload['subject'] ?? ''),
            text: (string) ($payload['text'] ?? ''),
            inReplyTo: isset($payload['in_reply_to']) ? (string) $payload['in_reply_to'] : null,
            updatedAt: new DateTimeImmutable((string) ($payload['updated_at'] ?? 'now')),
        );
    }

    public function delete(string $id): void
    {
        @unlink($this->path . '/' . rawurlencode($id) . '.json');
    }
}

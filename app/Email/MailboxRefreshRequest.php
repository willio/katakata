<?php

declare(strict_types=1);

namespace Katakata\Email;

use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;

final class MailboxRefreshRequest
{
    public function __construct(
        private readonly string $path,
        private readonly AtomicFile $files,
    ) {
    }

    public function request(?DateTimeImmutable $at = null): void
    {
        $at ??= new DateTimeImmutable();
        $this->files->write($this->path, json_encode([
            'requested_at' => $at->format(DATE_ATOM),
        ], JSON_THROW_ON_ERROR) . "\n");
        @chmod($this->path, 0600);
    }

    public function consume(): bool
    {
        return is_file($this->path) && unlink($this->path);
    }
}

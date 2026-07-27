<?php

declare(strict_types=1);

namespace Katakata\Editorial;

use DateTimeImmutable;
use InvalidArgumentException;
use Katakata\Content\Draft;

final class DraftEditor
{
    public function __construct(
        private readonly string $draftsPath,
        private readonly AtomicFile $files,
        private readonly RevisionStore $revisions,
    ) {
    }

    /** @param array<string, mixed> $meta */
    public function save(string $slug, string $title, string $body, array $meta = []): string
    {
        $this->assertSlug($slug);
        if (trim($title) === '') {
            throw new InvalidArgumentException('Draft title cannot be empty.');
        }

        $path = $this->path($slug);
        $this->revisions->capture($slug, $path);
        $meta = ['title' => trim($title)] + $meta;
        $meta['updated_at'] = (new DateTimeImmutable())->format(DateTimeImmutable::ATOM);
        $this->files->write($path, Document::markdown($meta, $body));

        return $path;
    }

    public function schedule(Draft $draft, DateTimeImmutable $at): string
    {
        $meta = $draft->meta;
        unset($meta['title'], $meta['updated_at']);
        $meta['status'] = 'scheduled';
        $meta['publish_at'] = $at->format(DateTimeImmutable::ATOM);

        return $this->save($draft->slug, $draft->title, $draft->body, $meta);
    }

    private function path(string $slug): string
    {
        return $this->draftsPath . '/' . $slug . '.md';
    }

    private function assertSlug(string $slug): void
    {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new InvalidArgumentException("Invalid draft slug [{$slug}].");
        }
    }
}

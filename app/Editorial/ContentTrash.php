<?php

declare(strict_types=1);

namespace Katakata\Editorial;

use DateTimeImmutable;
use Katakata\Content\Draft;
use Katakata\Content\Post;
use RuntimeException;

final class ContentTrash
{
    public function __construct(
        private readonly string $contentRoot,
        private readonly AtomicFile $files,
        private readonly RevisionStore $revisions,
    ) {
    }

    public function trashDraft(Draft $draft, string $actorId, ?string $reason = null): TrashItem
    {
        return $this->trash('draft', $draft->slug, $draft->title, $draft->path, $actorId, $reason);
    }

    public function trashPost(Post $post, string $actorId, ?string $reason = null): TrashItem
    {
        return $this->trash('post', $post->slug, $post->title, $post->path, $actorId, $reason);
    }

    /** @return array<int, TrashItem> */
    public function all(): array
    {
        $items = [];
        foreach (['draft', 'post'] as $type) {
            foreach (glob($this->trashDirectory($type) . '/*.json') ?: [] as $manifestPath) {
                try {
                    $items[] = $this->readManifest($type, basename($manifestPath, '.json'));
                } catch (\Throwable) {
                    continue;
                }
            }
        }
        usort($items, static fn (TrashItem $a, TrashItem $b): int => strcmp($b->trashedAt, $a->trashedAt));
        return $items;
    }

    public function restore(string $type, string $id): string
    {
        $item = $this->readManifest($type, $id);
        $source = $this->trashPath($type, $id, 'md');
        $contents = $this->verifiedContents($item, $source);
        $destination = $this->canonicalPath($item->originalPath, $type);
        if (is_file($destination)) {
            throw new RuntimeException('The original content location is occupied.');
        }

        $this->files->write($destination, $contents);
        if (!hash_equals($item->sha256, hash_file('sha256', $destination) ?: '')) {
            @unlink($destination);
            throw new RuntimeException('Unable to verify restored content.');
        }
        if (!unlink($source) || !unlink($this->trashPath($type, $id, 'json'))) {
            throw new RuntimeException('Unable to complete Trash restoration.');
        }
        return $destination;
    }

    public function purge(string $type, string $id, string $confirmation): void
    {
        if (!hash_equals($id, $confirmation)) {
            throw new RuntimeException('Trash purge confirmation must exactly match the Trash ID.');
        }
        $item = $this->readManifest($type, $id);
        $source = $this->trashPath($type, $id, 'md');
        $this->verifiedContents($item, $source);
        if (!unlink($source) || !unlink($this->trashPath($type, $id, 'json'))) {
            throw new RuntimeException('Unable to purge Trash item.');
        }
    }

    private function trash(string $type, string $slug, string $title, string $source, string $actorId, ?string $reason): TrashItem
    {
        if (!is_file($source)) {
            throw new RuntimeException('Content is no longer available.');
        }
        $relative = $this->relativeSourcePath($source, $type);
        $contents = file_get_contents($source);
        if ($contents === false) {
            throw new RuntimeException('Unable to read content for Trash.');
        }
        $sha256 = hash('sha256', $contents);
        $now = new DateTimeImmutable('now');
        $id = $now->format('Ymd\THisv\Z') . '-' . substr($sha256, 0, 12);
        $markdownPath = $this->trashPath($type, $id, 'md');
        $manifestPath = $this->trashPath($type, $id, 'json');
        if (is_file($markdownPath) || is_file($manifestPath)) {
            throw new RuntimeException('Trash identity collision.');
        }

        $this->revisions->capture($slug, $source);
        $manifest = [
            'id' => $id,
            'type' => $type,
            'slug' => $slug,
            'title' => $title,
            'original_path' => $relative,
            'trashed_at' => $now->format(DATE_ATOM),
            'actor_id' => $actorId,
            'reason' => $reason,
            'sha256' => $sha256,
        ];

        try {
            $this->files->write($markdownPath, $contents);
            $this->files->write($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
            if (!hash_equals($sha256, hash_file('sha256', $markdownPath) ?: '')) {
                throw new RuntimeException('Unable to verify trashed content.');
            }
            if (!unlink($source)) {
                throw new RuntimeException('Unable to remove active content after Trash copy.');
            }
        } catch (\Throwable $error) {
            @unlink($markdownPath);
            @unlink($manifestPath);
            throw $error;
        }

        return $this->item($manifest);
    }

    private function readManifest(string $type, string $id): TrashItem
    {
        $this->assertIdentity($type, $id);
        $raw = @file_get_contents($this->trashPath($type, $id, 'json'));
        if ($raw === false) {
            throw new RuntimeException('Trash item was not found.');
        }
        $manifest = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || ($manifest['id'] ?? null) !== $id || ($manifest['type'] ?? null) !== $type) {
            throw new RuntimeException('Trash manifest is invalid.');
        }
        foreach (['slug', 'title', 'original_path', 'trashed_at', 'actor_id', 'sha256'] as $field) {
            if (!is_string($manifest[$field] ?? null) || $manifest[$field] === '') {
                throw new RuntimeException('Trash manifest is incomplete.');
            }
        }
        $this->canonicalPath($manifest['original_path'], $type);
        return $this->item($manifest);
    }

    /** @param array<string, mixed> $manifest */
    private function item(array $manifest): TrashItem
    {
        return new TrashItem(
            $manifest['id'], $manifest['type'], $manifest['slug'], $manifest['title'], $manifest['original_path'],
            $manifest['trashed_at'], $manifest['actor_id'], is_string($manifest['reason'] ?? null) ? $manifest['reason'] : null,
            $manifest['sha256'], $manifest,
        );
    }

    private function verifiedContents(TrashItem $item, string $path): string
    {
        $contents = @file_get_contents($path);
        if ($contents === false || !hash_equals($item->sha256, hash('sha256', $contents))) {
            throw new RuntimeException('Trash checksum does not match.');
        }
        return $contents;
    }

    private function relativeSourcePath(string $source, string $type): string
    {
        $resolvedRoot = realpath($this->contentRoot);
        if ($resolvedRoot === false) {
            throw new RuntimeException('Configured content root is unavailable.');
        }
        $root = rtrim($resolvedRoot, '/') . '/';
        $resolved = realpath($source);
        if ($resolved === false || !str_starts_with($resolved, $root)) {
            throw new RuntimeException('Content path is outside the configured root.');
        }
        $relative = substr($resolved, strlen($root));
        $this->canonicalPath($relative, $type);
        return $relative;
    }

    private function canonicalPath(string $relative, string $type): string
    {
        $expected = $type === 'draft' ? 'drafts/' : 'posts/';
        if (!str_starts_with($relative, $expected) || str_contains($relative, '..') || !str_ends_with($relative, '.md')) {
            throw new RuntimeException('Trash destination is invalid.');
        }
        $path = rtrim($this->contentRoot, '/') . '/' . $relative;
        $parent = realpath(dirname($path));
        $allowed = realpath(rtrim($this->contentRoot, '/') . '/' . rtrim($expected, '/'));
        if ($parent !== false && $allowed !== false && !str_starts_with($parent . '/', $allowed . '/')) {
            throw new RuntimeException('Trash destination is outside the configured root.');
        }
        return $path;
    }

    private function assertIdentity(string $type, string $id): void
    {
        if (!in_array($type, ['draft', 'post'], true) || !preg_match('/^[A-Za-z0-9T\-]+$/', $id)) {
            throw new RuntimeException('Trash identity is invalid.');
        }
    }

    private function trashDirectory(string $type): string
    {
        return rtrim($this->contentRoot, '/') . '/trash/' . $type . 's';
    }

    private function trashPath(string $type, string $id, string $extension): string
    {
        $this->assertIdentity($type, $id);
        return $this->trashDirectory($type) . '/' . $id . '.' . $extension;
    }
}

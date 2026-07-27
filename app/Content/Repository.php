<?php

declare(strict_types=1);

namespace Katakata\Content;

use DateTimeImmutable;
use Katakata\Application;

/**
 * The Content Repository.
 *
 * This is the only class in Katakata that turns raw Markdown files
 * into structured content objects. Every other part of the
 * application — controllers, commands, renderers — asks the
 * Repository for Posts, Drafts, Authors, and Assets. None of them
 * read content/ directly.
 *
 * A file that fails validation (missing required front matter, a
 * filename that doesn't match the storage convention, an
 * unparseable date) is skipped rather than crashing the build; its
 * error is recorded and available via errors().
 */
final class Repository
{
    private readonly Discovery $discovery;

    /** @var Collection<Post>|null */
    private ?Collection $posts = null;

    /** @var Collection<Draft>|null */
    private ?Collection $drafts = null;

    /** @var Collection<Author>|null */
    private ?Collection $authors = null;

    /** @var Collection<Asset>|null */
    private ?Collection $assets = null;

    /** @var array<int, string> */
    private array $errors = [];

    public function __construct(
        private readonly string $postsPath,
        private readonly string $draftsPath,
        private readonly string $authorsPath,
        private readonly string $assetsPath,
    ) {
        $this->discovery = new Discovery($postsPath, $draftsPath, $authorsPath, $assetsPath);
    }

    /**
     * Build a Repository from the application's configured content
     * paths (config/content.php), rather than a database or hardcoded
     * location — see ADR 0001.
     */
    public static function forApplication(Application $app): self
    {
        $config = $app->config();

        return new self(
            $app->basePath((string) $config->get('content.posts_path', 'content/posts')),
            $app->basePath((string) $config->get('content.drafts_path', 'content/drafts')),
            $app->basePath((string) $config->get('content.authors_path', 'content/authors')),
            $app->basePath((string) $config->get('content.assets_path', 'content/assets')),
        );
    }

    /**
     * @return Collection<Post> newest first
     */
    public function posts(): Collection
    {
        return $this->posts ??= $this->buildPosts();
    }

    /**
     * @return Collection<Draft>
     */
    public function drafts(): Collection
    {
        return $this->drafts ??= $this->buildDrafts();
    }

    /**
     * @return Collection<Author>
     */
    public function authors(): Collection
    {
        return $this->authors ??= $this->buildAuthors();
    }

    /**
     * @return Collection<Asset>
     */
    public function assets(): Collection
    {
        return $this->assets ??= $this->buildAssets();
    }

    public function findPost(string $slug): ?Post
    {
        foreach ($this->posts() as $post) {
            if ($post->slug === $slug) {
                return $post;
            }
        }

        return null;
    }

    public function findAuthor(string $slug): ?Author
    {
        foreach ($this->authors() as $author) {
            if ($author->slug === $slug) {
                return $author;
            }
        }

        return null;
    }

    /**
     * Files skipped due to validation errors during the last build,
     * as "path: reason" strings.
     *
     * @return array<int, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Discard cached content so the next call re-reads the filesystem.
     */
    public function refresh(): void
    {
        $this->posts = null;
        $this->drafts = null;
        $this->authors = null;
        $this->assets = null;
        $this->errors = [];
    }

    private function buildPosts(): Collection
    {
        $posts = [];

        foreach ($this->discovery->posts() as $path) {
            try {
                $posts[] = $this->parsePost($path);
            } catch (ContentValidationException $e) {
                $this->errors[] = "{$path}: {$e->getMessage()}";
            }
        }

        usort($posts, static fn (Post $a, Post $b): int => $b->date <=> $a->date);

        return new Collection($posts);
    }

    private function buildDrafts(): Collection
    {
        $drafts = [];

        foreach ($this->discovery->drafts() as $path) {
            try {
                $drafts[] = $this->parseDraft($path);
            } catch (ContentValidationException $e) {
                $this->errors[] = "{$path}: {$e->getMessage()}";
            }
        }

        return new Collection($drafts);
    }

    private function buildAuthors(): Collection
    {
        $authors = [];

        foreach ($this->discovery->authors() as $path) {
            try {
                $authors[] = $this->parseAuthor($path);
            } catch (ContentValidationException $e) {
                $this->errors[] = "{$path}: {$e->getMessage()}";
            }
        }

        return new Collection($authors);
    }

    private function buildAssets(): Collection
    {
        $assets = [];

        foreach ($this->discovery->assets() as $path) {
            $bytes = filesize($path);
            $assets[] = new Asset(basename($path), $path, $bytes === false ? 0 : $bytes, $this->mimeType($path));
        }

        return new Collection($assets);
    }

    private function parsePost(string $path): Post
    {
        $relative = ltrim(substr($path, strlen($this->postsPath)), '/');
        $filename = $this->parsePostFilename($relative);

        [$meta, $body] = $this->read($path);

        $title = $this->requireString($meta, 'title');
        $slug = is_string($meta['slug'] ?? null) && $meta['slug'] !== '' ? $meta['slug'] : $filename['slug'];
        $date = isset($meta['date']) ? $this->parseDate((string) $meta['date']) : $filename['date'];

        return new Post(
            slug: $slug,
            title: $title,
            date: $date,
            author: isset($meta['author']) ? (string) $meta['author'] : null,
            tags: array_map('strval', (array) ($meta['tags'] ?? [])),
            excerpt: isset($meta['excerpt']) ? (string) $meta['excerpt'] : null,
            status: isset($meta['status']) ? (string) $meta['status'] : 'published',
            body: $body,
            meta: $meta,
            path: $path,
        );
    }

    private function parseDraft(string $path): Draft
    {
        [$meta, $body] = $this->read($path);

        $title = $this->requireString($meta, 'title');
        $updatedAt = isset($meta['updated_at'])
            ? $this->parseDate((string) $meta['updated_at'])
            : $this->fileModifiedAt($path);

        return new Draft(
            slug: basename($path, '.md'),
            title: $title,
            updatedAt: $updatedAt,
            body: $body,
            meta: $meta,
            path: $path,
        );
    }

    private function parseAuthor(string $path): Author
    {
        [$meta, $body] = $this->read($path);

        return new Author(
            slug: basename($path, '.md'),
            name: $this->requireString($meta, 'name'),
            bio: $body !== '' ? $body : (isset($meta['bio']) ? (string) $meta['bio'] : null),
            avatar: isset($meta['avatar']) ? (string) $meta['avatar'] : null,
            meta: $meta,
            path: $path,
        );
    }

    /**
     * @return array{0: array<string, mixed>, 1: string} [meta, body]
     */
    private function read(string $path): array
    {
        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new ContentValidationException('Unable to read file.');
        }

        $parsed = FrontMatter::parse($raw);

        return [$parsed['meta'], $parsed['body']];
    }

    /**
     * @return array{date: DateTimeImmutable, slug: string}
     */
    private function parsePostFilename(string $relative): array
    {
        $pattern = '#^(\d{4})/(\d{2})/(\d{2})(\d{2})(\d{2})_([a-z0-9]+(?:-[a-z0-9]+)*)\.md$#';

        if (!preg_match($pattern, $relative, $m)) {
            throw new ContentValidationException(
                'Filename must match content/posts/YYYY/MM/yymmdd_slug.md, got "' . $relative . '".',
            );
        }

        [, $folderYear, $folderMonth, $yy, $mm, $dd, $slug] = $m;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', "20{$yy}-{$mm}-{$dd}");

        if ($date === false) {
            throw new ContentValidationException("Filename date [20{$yy}-{$mm}-{$dd}] is invalid.");
        }

        if ($date->format('Y') !== $folderYear || $date->format('m') !== $folderMonth) {
            throw new ContentValidationException(
                "Filename date [{$date->format('Y-m-d')}] doesn't match its folder [{$folderYear}/{$folderMonth}].",
            );
        }

        return ['date' => $date, 'slug' => $slug];
    }

    private function parseDate(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($date === false) {
            $date = DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $value);
        }

        if ($date === false) {
            throw new ContentValidationException("Date [{$value}] is not a recognizable date.");
        }

        return $date;
    }

    private function fileModifiedAt(string $path): ?DateTimeImmutable
    {
        $mtime = filemtime($path);

        return $mtime === false ? null : (new DateTimeImmutable())->setTimestamp($mtime);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function requireString(array $meta, string $key): string
    {
        if (!isset($meta[$key]) || !is_string($meta[$key]) || $meta[$key] === '') {
            throw new ContentValidationException("Missing required front matter field [{$key}].");
        }

        return $meta[$key];
    }

    private function mimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}

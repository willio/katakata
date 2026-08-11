# Subsystem: Content Engine

Phase: 1

## Purpose

The Content Engine turns files on disk under `content/` into
structured objects the rest of the application can use, and is the
only part of Katakata that reads Markdown directly — per the Master
Specification's rule: "Applications never read Markdown directly.
Everything goes through the repository."

## Pipeline

```
Filesystem → Discovery → Validation → Front Matter → Markdown → Post Object → Repository
```

- **Discovery** (`Katakata\Content\Discovery`) finds files on disk:
  posts under `content/posts/YYYY/MM/*.md`, drafts under
  `content/drafts/*.md`, authors under `content/authors/*.md`, and any
  file under `content/assets/` (recursively, skipping dotfiles).
  Discovery never parses or validates — it only locates.
- **Front Matter** (`Katakata\Content\FrontMatter`) splits a file's
  raw text into metadata and body. See ADR 0005 for why this is a
  deliberately restricted YAML subset rather than a full parser.
- **Validation** happens inline while building each content object: a
  post's filename must match `YYYY/MM/yymmdd_slug.md` (and the folder
  must agree with the date encoded in the filename), and posts/drafts
  require a `title`, authors require a `name`. A file that fails
  validation is skipped — not fatal to the rest of the build — and its
  error is recorded.
- **Repository** (`Katakata\Content\Repository`) is the public API:
  `posts()`, `drafts()`, `authors()`, `assets()` (each returning a
  `Collection`), plus `findPost(string $slug)`, `findAuthor(string
  $slug)`, and `errors()` for anything skipped during the last build.
  Results are cached after the first build; call `refresh()` to force
  a re-read of the filesystem.

## Content Objects

| Object | Required front matter | Notable optional fields |
|---|---|---|
| `Post` | `title` | `date`, `slug`, `author`, `tags`, `excerpt`, `status` (defaults to `published`) |
| `Draft` | `title` | `updated_at` (falls back to the file's mtime) |
| `Author` | `name` | `avatar`; flat `social` HTTPS URL list; `bio` falls back to the file's Markdown body if no `bio` front matter field is set |
| `Asset` | — (no front matter; discovered by presence) | — |

A `Post`'s `date` and `slug` can be overridden by front matter; if
absent, both are derived from the filename
(`content/posts/2026/01/260115_hello-world.md` → date `2026-01-15`,
slug `hello-world`).

Author `social` values are deliberately a flat list, in line with ADR 0005.
Only well-formed HTTPS URLs with a host are exposed by the repository; callers
must derive their display label from the URL rather than store a nested social
network object.

`Collection` (`Katakata\Content\Collection`) is a small, immutable,
typed collection — `filter()`, `sort()`, and `first()` all return new
collections rather than mutating in place.

## Wiring

`Repository::forApplication()` builds a Repository from
`config/content.php`'s configured paths, and `bootstrap/app.php`
registers it as a singleton — so HTTP, CLI, and tests all share one
instance per request/process, consistent with the shared bootstrap
principle from Phase 0.

## CLI

```bash
php bin/katakata content:list      # Print posts, drafts, authors, assets
php bin/katakata content:validate  # Exit 0 if all content is valid, 1 with details otherwise
```

## What's Deliberately Not Here

- No rendering of Markdown to HTML — that's the Renderer, Phase 2.
- No filesystem watching or auto-refresh — call `refresh()` explicitly
  if you need to observe changes within a single long-running process.
- No caching layer beyond the Repository's own in-memory cache for the
  lifetime of a request/process — anything more belongs to the
  "Caching" section of the Master Specification and is out of scope
  until a real performance need appears.

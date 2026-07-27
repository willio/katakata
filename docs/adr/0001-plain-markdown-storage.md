# ADR 0001: Plain Markdown Storage

## Status

Accepted

## Context

Katakata's philosophy holds that writing should outlive software, and
that content belongs to its author. A database-backed content model
ties an author's work to a specific application, schema version, and
export process. If the software disappears, so does the ability to
read the writing without it.

## Decision

Canonical content — posts, drafts, authors, assets — is stored as
plain files on disk under `content/`, using Markdown with front
matter for posts and authors. There is no database of record for
content. The filesystem is the source of truth.

Published articles live at `content/posts/YYYY/MM/yymmdd_slug.md`;
drafts, authors, and assets each have their own top-level directory
under `content/`.

Every other representation — rendered HTML, RSS, JSON Feed, search
indexes, newsletters, Threads posts — is generated from these files
and is disposable. Any of them can be deleted and rebuilt from
Markdown without data loss.

## Consequences

- Writers can read, edit, back up, and version-control their own
  content with ordinary tools (a text editor, `git`, `rsync`) with no
  dependency on Katakata being installed or running.
- The Content Engine's only dependency is the filesystem — no
  database migrations gate a fresh install.
- Search indexes, caches, and derived feeds must be treated as
  rebuildable and never as the only copy of anything.
- Concurrent or high-frequency writes need care (see ADR 0003 and the
  Master Specification's "Security" section on atomic file writes),
  since the filesystem doesn't give us transactions the way a database
  would.
- Multi-author or multi-instance deployments will eventually need a
  story for concurrent file access; this is deferred until Phase 1
  makes it concrete rather than solved speculatively now.

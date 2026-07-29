# Katakata
## Master Specification
### Revision 2.1

> **Status:** Canonical Product Specification
>
> This document defines the vision, philosophy, architecture, and guiding principles of Katakata.
>
> All subsystem specifications, ADRs, and implementation documents derive from this document.

---

# Vision

Katakata is a calm, typography-first publishing platform built around plain Markdown files.

It is designed for writers who value ownership, permanence, simplicity, and the open web.

A website should be the permanent home of writing.

Social platforms are distribution channels—not destinations.

Katakata owns the writing.

Threads hosts the conversation.

Markdown remains the canonical record.

---

# Philosophy

Katakata is built around a few simple beliefs.

- Writing should outlive software.
- Content belongs to its author.
- Software should disappear behind the writing.
- The interface should be quiet.
- The architecture should remain understandable.
- Simplicity is a long-term competitive advantage.

---

# Product Principles

## Writers first

Every design decision begins with the writing experience.

## Reader first

Reading should remain fast, distraction-free, and accessible.

## Open by default

Content should remain portable.

## Files over databases

Canonical content is stored as ordinary files.

## Progressive enhancement

The website should remain usable without JavaScript.

## Calm software

Every feature must justify its existence.

---

# Product Goals

Katakata enables writers to:

- publish essays
- maintain journals
- run newsletters
- document projects
- collaborate with multiple authors
- build permanent archives
- understand publication health through privacy-bounded analytics

from one canonical source.

---

# Canonical Source

Markdown is the only source of truth.

```
Markdown

↓

Everything else
```

Generated outputs include:

- HTML
- RSS
- JSON Feed
- Newsletter
- Search Index
- Threads
- Future distribution targets

Generated artifacts are disposable.

Markdown is not.

---

# Architecture

Katakata is built from independent subsystems.

```
Markdown

↓

Content Engine

↓

Repository

↓

Renderer

↓

Publishing

↓

Distribution
```

Every subsystem depends upon the Content Engine.

The Content Engine depends only upon the filesystem.

---

# Repository Structure

```
app/
config/
content/
docs/
public/
resources/
routes/
storage/
tests/
```

Responsibilities are intentionally separated.

Only `public/` is web-accessible.

---

# Content Model

The Content Engine exposes structured content objects.

Primary objects:

- Post
- Author
- Draft
- Asset
- Collection

Everything else is derived.

---

# Content Storage

Canonical content lives under:

```
content/

    posts/

    drafts/

    authors/

    assets/
```

Published articles:

```
content/posts/YYYY/MM/yymmdd_slug.md
```

Authors:

```
content/authors/author.md
```

Drafts:

```
content/drafts/
```

Assets:

```
content/assets/
```

---

# Content Pipeline

```
Filesystem

↓

Discovery

↓

Validation

↓

Front Matter

↓

Markdown

↓

Post Object

↓

Repository
```

Applications never read Markdown directly.

Everything goes through the repository.

---

# Publishing Pipeline

One Markdown document produces multiple outputs.

```
Markdown

↓

Renderer

↓

Website

RSS

JSON Feed

Newsletter

Threads

Search
```

The article is written once.

Distribution is automatic.

---

# Writer Experience

Katakata should disappear while writing.

Core principles:

- distraction-free
- keyboard-first
- Markdown-native
- autosave
- drafts
- scheduling
- revisions
- live preview

No unnecessary interface.

---

# Reader Experience

Reading should be:

- beautiful
- fast
- accessible
- timeless
- typography-first

The interface should never compete with the content.

---

# Owner Dashboard

The authenticated dashboard is the owner's orientation surface after login.

It answers two questions without becoming a second content editor:

- What is happening?
- What should I do next?

Content status is always useful without analytics. Observational analytics use
the narrow SQLite exception accepted by ADR 0009; Markdown remains canonical
and files remain authoritative for every authored object. Raw IP addresses
are never stored. Dashboard failures never affect reading, editing, or
publishing.

# Newsletter

Newsletter generation is built into publishing.

There is no separate newsletter editor.

Every newsletter originates from the same Markdown document.

---

# Threads Integration

Threads is not Katakata's publishing platform.

Threads is Katakata's conversation layer.

The distinction is intentional.

Katakata exists to preserve writing. Threads exists to amplify and discuss it.

Every article has one canonical home:

```
Katakata
```

Every discussion has one canonical home:

```
Threads
```

Neither system attempts to replace the other.

---

## Separation of Responsibilities

Katakata owns:

- writing
- drafts
- revisions
- permanent URLs
- archives
- RSS
- newsletters
- search
- metadata
- long-form reading

Threads owns:

- conversations
- replies
- likes
- reposts
- discovery
- social distribution
- engagement

This separation allows each platform to do what it does best.

---

## Publish Flow

Publishing begins with Markdown.

```
Markdown

↓

Content Engine

↓

Repository

↓

Renderer

↓

katakata.example/article

↓

Publishing Pipeline

        ├── Website
        ├── RSS
        ├── JSON Feed
        ├── Newsletter
        └── Threads
```

Every output originates from the same Post object.

No channel maintains its own version of the article.

---

## Threads Adapter

Threads is implemented as a publishing adapter.

```
Publishing Pipeline

        │

        ├── HTML Adapter

        ├── RSS Adapter

        ├── Newsletter Adapter

        └── Threads Adapter
```

Adapters receive structured content from the Publishing Pipeline.

They never read Markdown directly.

This keeps distribution platforms independent of the Content Engine.

Adding another platform should require only a new adapter.

---

## Threads Post Model

A Threads post is considered a publication derivative.

It is generated from the canonical article.

Typical publication may include:

- title
- opening excerpt
- hero image
- article URL
- optional call to discussion

The exact formatting may evolve without changing the underlying article.

---

## Discussion Identity

Each published article may maintain an associated Threads discussion.

The relationship is one-to-one.

```
Article

↓

Threads Conversation
```

Katakata stores only the metadata required to reference that discussion.

Examples include:

- Threads post identifier
- publication timestamp
- publication status
- synchronization state

The conversation itself remains on Threads.

---

## Discussion on Katakata

Katakata may surface discussion metadata alongside an article.

Examples include:

- reply count
- repost count
- like count
- recent replies
- "Continue the discussion on Threads"

These are presentation features.

They are never considered canonical content.

If Threads data becomes unavailable, the article remains fully readable.

---

## Synchronization

Katakata should synchronize with Threads through official APIs.

Synchronization may include:

- publication status
- engagement metrics
- reply summaries
- moderation events
- webhooks
- account verification

Synchronization enriches the article.

It never replaces it.

---

## Failure Model

Threads is treated as an optional downstream service.

Publishing an article must never depend on Threads being available.

Example:

```
Publish Article

↓

Website ✓

↓

Newsletter ✓

↓

RSS ✓

↓

Threads ✗
```

The article remains published.

Threads publication can be retried later.

This principle applies to every distribution adapter.

---

## Editing

The source article is edited only inside Katakata.

Editing an article creates a new canonical revision.

The publishing pipeline determines whether downstream channels should:

- remain unchanged
- publish an update
- publish a follow-up
- synchronize metadata only

Katakata never edits content directly on Threads unless explicitly configured.

---

## Moderation

Conversation moderation belongs to Threads.

Editorial moderation belongs to Katakata.

Each platform governs its own domain.

Katakata does not attempt to recreate a comment system.

---

## Multi-Author Support

Each author may connect an independent Threads account.

Publishing uses the author's configured identity.

This enables:

- personal publications
- newsroom workflows
- publications with multiple contributors
- organizational accounts

Identity belongs to the author, not the application.

---

## Future Platforms

Threads is the first social adapter.

The architecture intentionally allows additional adapters.

Examples include:

- Mastodon
- Bluesky
- LinkedIn
- Medium
- Dev.to
- Hashnode
- Ghost
- future publishing platforms

Each adapter implements the same publishing contract.

The Content Engine remains unchanged.

---

## Design Principles

The integration is governed by five principles.

### Canonical First

The article always belongs to Katakata.

### Distribution, Never Migration

Content is distributed to social platforms.

Ownership never leaves Katakata.

### Loose Coupling

Social platforms are replaceable.

The publishing architecture is not.

### Graceful Degradation

If Threads disappears, Katakata continues to function normally.

Only one adapter is lost.

### Open Web First

The website remains the primary destination.

Social platforms extend reach.

They do not become the archive.

---

## Guiding Principle

> Katakata preserves ideas.
>
> Threads grows conversations.
>
> One owns the work.
>
> The other expands its reach.

---

# Multi-Author

Authors are first-class content.

Each author owns:

- profile
- biography
- avatar
- archive
- newsletter
- Threads identity

Authors remain ordinary Markdown documents.

---

# Search

Search indexes content.

Search never becomes canonical storage.

Deleting the search index must never lose content.

---

# Caching

Caches improve performance.

Caches never become authoritative.

Every cache must be reproducible from canonical content.

---

# Operational Data Strategy

Katakata separates canonical content, operational state, analytical data, and published output.

## Tier 1 — Canonical Content

Markdown files under `content/` remain the only source of truth for articles, drafts, authors, and their durable metadata.

No database may become authoritative for canonical content.

## Tier 2 — Operational Index

SQLite may be used for operational concerns that benefit from fast indexed access or transactional state.

Approved uses include:

- full-text search through FTS5
- article, tag, author, and archive indexes
- metadata caches
- incremental build manifests
- related-content indexes
- sessions and queues when later required

SQLite state must be reproducible from canonical content or other durable configuration wherever applicable. Deleting the operational index must never delete an article or prevent a full rebuild.

## Tier 3 — Analytical Warehouse

DuckDB may be used for analytical workloads over publication and engagement datasets.

Approved uses include:

- readership and publication trends
- article and author performance
- newsletter metrics
- Threads engagement
- dashboard datasets
- historical and cohort analysis

Analytics should be captured as append-only events and materialized into open formats such as Parquet. DuckDB queries these datasets but does not own canonical content or participate in the critical publishing path.

## Tier 4 — Published Outputs

Published HTML, RSS, JSON Feed, newsletters, search artifacts, and distribution payloads are derived outputs.

All published outputs must remain reproducible from canonical content and configuration.

## Data Flow

```
Markdown (canonical)
        │
        ▼
Content Engine
        │
        ├──────────────► Renderer / Publishing Outputs
        │
        ├──────────────► SQLite Operational Index
        │                  ├── Search
        │                  ├── Metadata cache
        │                  └── Incremental build state
        │
        └──────────────► Append-only Analytics Events
                           │
                           ▼
                        Parquet
                           │
                           ▼
                        DuckDB
                           │
                           ▼
                     Reports / Insights
```

## Failure Boundaries

- Publishing must work without DuckDB.
- A missing SQLite index must be recoverable through a deterministic rebuild.
- Analytics failures must never block publishing.
- Neither SQLite nor DuckDB may contain the only durable copy of article content.

---

# Runtime

Katakata uses a lightweight application kernel.

HTTP, CLI, workers, and tests share the same bootstrap.

Configuration is immutable after startup.

Business logic remains framework-independent.

---

# Security

Security is enabled by default.

Principles:

- validate input
- escape output
- verify webhooks
- atomic file writes
- secure defaults
- least privilege

Canonical content must never be corrupted by partial writes.

---

# Performance

Optimize for:

1. clarity
2. correctness
3. measured performance

Avoid speculative optimization.

---

# Documentation

Project documentation consists of:

```
README

Master Specification

Roadmap

ADRs

Subsystem Specifications
```

Each serves a distinct purpose.

---

# Roadmap

## Phase 0

Foundation

- repository
- bootstrap
- configuration
- routing
- documentation

## Phase 1

Content Engine

- repository
- indexing
- drafts
- metadata
- assets

## Phase 2

Rendering

- website
- archives
- feeds
- typography

## Phase 3

Editorial

- editor
- revisions
- scheduling
- publishing

## Phase 4

Dashboard and Analytics

- owner dashboard
- privacy-bounded visit analytics
- recent activity and regional aggregates
- basic SEO checks

## Phase 5

Distribution

- newsletter
- Threads
- webhooks
- engagement synchronization

## Phase 6

Extensibility

- plugins
- themes
- APIs
- additional publishers

## Phase 6

Operational Index

- SQLite schema and migrations
- FTS5 search
- metadata and archive indexes
- incremental build manifest
- deterministic rebuild commands

## Phase 7

Analytics Platform

- append-only event model
- Parquet export pipeline
- DuckDB query service
- publication, readership, newsletter, and Threads reports

---

# Architecture Decision Records

Every significant architectural decision is documented through ADRs.

Examples:

- Plain Markdown Storage
- PHP Runtime
- Static-first Architecture
- Threads Discussion Layer
- Operational and Analytical Data Layers

ADRs explain **why** decisions exist.

Subsystem documents explain **how** they work.

---

# Non-Negotiable Rules

- Markdown is canonical.
- Files are authoritative.
- Generated artifacts are disposable.
- SQLite is supporting operational infrastructure, never canonical content storage.
- DuckDB is supporting analytical infrastructure, never required for publishing.
- Writers own their content.
- Threads hosts discussion.
- Katakata owns publication.
- Controllers remain thin.
- Business logic is framework-independent.
- Every subsystem has a single responsibility.
- Every feature must justify its complexity.

---

# Guiding Principle

> Write once.
>
> Own forever.
>
> Publish anywhere.
>
> Katakata is built for writers—not platforms.

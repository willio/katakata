# ADR 0003: Static-first Architecture

## Status

Accepted

## Context

The Master Specification requires that "the website should remain
usable without JavaScript" and that reading be "fast, distraction-free,
and accessible." Reader-facing pages are, by nature, mostly static:
an article's rendered HTML doesn't change between requests unless the
canonical Markdown changes.

## Decision

Katakata treats rendered output — HTML pages, RSS, JSON Feed — as
artifacts that are generated from canonical Markdown and served as
directly as possible, rather than recomputed from scratch on every
request via heavy server-side logic. The Content Pipeline
(Filesystem → Discovery → Validation → Front Matter → Markdown → Post
Object → Repository) sits upstream of rendering; rendering itself
should be cheap and cacheable, because its inputs are stable files,
not live database queries.

Concretely, this means:

- Every reader-facing route should be servable without requiring
  JavaScript to execute in the browser.
- Generated artifacts (HTML, feeds, search indexes) are treated as
  reproducible caches of canonical content (see ADR 0001), which
  makes aggressive caching and even fully static regeneration
  reasonable strategies as the Renderer subsystem (Phase 2) is built.
- Dynamic behavior (search, drafts, the editor, Threads sync) is
  layered on top of a static-capable core, not required for it.

## Consequences

- Reading remains fast and resilient even under load, since the
  expensive work (parsing Markdown, computing metadata) happens at
  publish time, not at every page view.
- The architecture stays compatible with simple hosting (a plain
  webserver, a CDN) rather than requiring an application server to be
  warm and healthy for every reader request.
- Any caching layer introduced later must satisfy the Master
  Specification's rule that "every cache must be reproducible from
  canonical content" — caches accelerate; they never become
  authoritative.
- Features that inherently require dynamic, per-request computation
  (e.g. personalized views) are out of scope unless a future ADR
  revisits this principle.

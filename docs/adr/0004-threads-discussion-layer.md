# ADR 0004: Threads Discussion Layer

## Status

Accepted

## Context

Writers want their work to be discussed and distributed on social
platforms, but the Master Specification is explicit that "Katakata
owns the writing. Threads hosts the conversation." Coupling
publication to a social platform's availability, or letting that
platform become an alternate copy of the article, would violate
"Canonical First" and "Distribution, Never Migration."

## Decision

Threads is implemented as one adapter among several in the Publishing
Pipeline (`HTML Adapter`, `RSS Adapter`, `Newsletter Adapter`,
`Threads Adapter`, …), sitting downstream of the same `Post` object
every other channel consumes. Adapters never read Markdown directly —
only structured content from the pipeline — which keeps distribution
platforms independent of the Content Engine.

Katakata stores only the metadata needed to reference a Threads
discussion (post identifier, publication timestamp, publication
status, synchronization state) — never the conversation itself, which
remains on Threads. Each article maintains at most a one-to-one
relationship with a Threads discussion.

Publishing to Threads is treated as an optional, retryable downstream
step:

```
Publish Article
  Website ✓
  Newsletter ✓
  RSS ✓
  Threads ✗   ← article still published; retry later
```

Editorial moderation belongs to Katakata; conversation moderation
belongs to Threads. Katakata does not attempt to recreate a comment
system, and it never edits content directly on Threads unless
explicitly configured to do so.

## Consequences

- If Threads becomes slow, unavailable, or is discontinued, articles
  remain fully readable and every other distribution channel keeps
  working — only the Threads adapter is affected.
- Adding a future social platform (Mastodon, Bluesky, LinkedIn, …)
  should require only a new adapter implementing the same publishing
  contract, not changes to the Content Engine or Repository.
- Engagement data shown alongside an article (reply/repost/like
  counts, recent replies) is a presentation feature layered on top of
  synced metadata — never canonical content, and never required for
  the article to render.
- Multi-author setups can let each author connect an independent
  Threads identity; publishing uses the author's configured identity
  rather than a single application-wide account.
- This adapter pattern is the template Phase 4 (Distribution) and
  Phase 5 (Extensibility) build on for every subsequent publishing
  target.

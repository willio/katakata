# Roadmap

The build proceeds in seven phases. Each phase produces a working,
independently useful increment. The repository is the source of truth; phase
status reflects merged implementation contracts rather than aspiration.

## Phase 0 — Foundation ✅

- [x] Repository structure
- [x] Bootstrap
- [x] Configuration
- [x] Routing
- [x] Documentation

## Phase 1 — Content Engine ✅

- [x] Repository
- [x] Indexing
- [x] Drafts
- [x] Metadata / front matter parsing
- [x] Assets

See [`docs/subsystems/content-engine.md`](./subsystems/content-engine.md).

## Phase 2 — Rendering ✅

- [x] Plain PHP view foundation
- [x] Markdown renderer
- [x] Canonical article routes and view
- [x] Chronological and author archives
- [x] RSS and JSON Feed
- [x] Typography system

See [`docs/subsystems/rendering.md`](./subsystems/rendering.md).

## Phase 3 — Editorial ✅

- [x] Invite-only email/password authentication and passkeys
- [x] Fullscreen Markdown editor
- [x] Automatic title from the first body line
- [x] Automatic new-draft slug from title
- [x] Quiet autosave with local recovery and exceptional warning toasts
- [x] Canonical revisions
- [x] Scheduling and publishing

See [`docs/subsystems/editorial.md`](./subsystems/editorial.md) and
[`docs/design_specification.md`](./design_specification.md).

## Phase 4 — Dashboard and Analytics 🚧

This is the immediate build priority. It gives the owner a useful home view
and establishes privacy-bounded operational insight before more downstream
distribution providers increase system activity.

1. [x] Add the SQLite analytics store, schema boot, and deployment check.
2. [x] Record privacy-bounded visits without persisting raw IP addresses.
3. [x] Add summary queries and the 400-day prune command.
4. [x] Add reproducible SEO checks and `seo:check`.
5. [x] Build the content-backed dashboard shell: published count, draft count,
   recent drafts, and latest posts.
6. [x] Add visits, trends, and recent visits with failure-isolated analytics loading.
7. [ ] Add regional aggregates and the visitor map only after region derivation and disclosure copy are
   verified.
8. [ ] Add The Buzz after the Threads read adapter exists.

See [`docs/subsystems/dashboard.md`](./subsystems/dashboard.md),
[`docs/subsystems/analytics.md`](./subsystems/analytics.md), and
[ADR 0009](./adr/0009-sqlite-analytics-seo.md).

## Phase 5 — Distribution 🚧

Work has resumed on the provider-independent distribution stack while Phase 4's regional and Threads-dependent dashboard items remain deliberately deferred.

- [x] Adapter boundary and per-channel failure isolation
- [x] Provider-neutral newsletter payload/outbox
- [x] Filesystem-backed newsletter subscriber consent and storage
- [x] Public subscribe, confirm, and unsubscribe routes
- [x] Provider-independent email transport, durable attempts, idempotency, and retries
- [x] Automatic idempotent post-publication newsletter dispatch
- [x] Production email provider (Resend)
- [x] Threads publish/read adapters
- [ ] Webhooks and retry processing
- [ ] Engagement metadata synchronization

See [`docs/subsystems/distribution.md`](./subsystems/distribution.md).

## Phase 6 — Extensibility

- [ ] Plugins
- [ ] Themes
- [ ] Public APIs
- [ ] Additional publisher adapters

---

Each active phase has a subsystem document under
[`docs/subsystems/`](./subsystems). Architectural exceptions require an ADR
before implementation.

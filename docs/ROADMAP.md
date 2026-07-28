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
6. [ ] Add visits, trends, recent visits, and regional aggregates.
7. [ ] Add the visitor map only after region derivation and disclosure copy are
   verified.
8. [ ] Add The Buzz after the Threads read adapter exists.

See [`docs/subsystems/dashboard.md`](./subsystems/dashboard.md),
[`docs/subsystems/analytics.md`](./subsystems/analytics.md), and
[ADR 0009](./adr/0009-sqlite-analytics-seo.md).

## Phase 5 — Distribution 🚧

The provider-independent foundation already exists; provider work resumes
after the Phase 4 owner/analytics boundary is operational.

- [x] Adapter boundary and per-channel failure isolation
- [x] Provider-neutral newsletter payload/outbox
- [ ] Newsletter subscriber consent, storage, and transport
- [ ] Threads publish/read adapters
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

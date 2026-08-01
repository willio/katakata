# Roadmap

The build proceeds in independently useful increments. The repository is the
source of truth; status reflects implemented contracts rather than aspiration.

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
- [x] Reader-facing homepage with latest articles, author context, newsletter signup, and feed discovery

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

## Phase 4 — Dashboard and Analytics ✅

- [x] SQLite analytics store, schema boot, and deployment check
- [x] Privacy-bounded visit recording without raw IP persistence
- [x] Summary queries and 400-day prune command
- [x] Reproducible SEO checks and `seo:check`
- [x] Sparse owner dashboard with linked Visits, Posts, Drafts, and Inbox cards
- [x] Canonical `/analytics` route
- [x] Filtered `/posts?status=all|drafts|scheduled|published` workspace
- [x] Failure-isolated visit summaries and recent visits
- [x] Failure-isolated, read-only discussion activity
- [ ] Regional aggregates and visitor map after derivation and disclosure review

See [`docs/subsystems/dashboard.md`](./subsystems/dashboard.md),
[`docs/subsystems/analytics.md`](./subsystems/analytics.md), and
[ADR 0009](./adr/0009-sqlite-analytics-seo.md).

## Phase 5 — Distribution and Mail ✅

- [x] Adapter boundary and per-channel failure isolation
- [x] Provider-neutral newsletter payload/outbox
- [x] Filesystem-backed subscriber consent and storage
- [x] Public subscribe, confirm, and unsubscribe routes
- [x] Provider-independent email transport, durable attempts, idempotency, and retries
- [x] Automatic idempotent post-publication newsletter dispatch
- [x] Production email provider integration
- [x] Authenticated delivery webhooks and delivery-state reconciliation
- [x] Provider-neutral mailbox boundary
- [x] Safe unavailable mailbox provider with non-secret readiness state
- [x] Unified `/mail` workspace for Inbox readiness and campaign work
- [x] Combined Mail attention model surfaced on the dashboard
- [x] `/dashboard/mail` compatibility redirect to `/mail`
- [ ] Scheduled IMAP synchronization adapter and cached operational inbox
- [ ] Guided Mail connection setup in `/dashboard/settings`, including safe
  non-secret connection readiness; IMAP credentials are provisioned only via
  environment variables or the host secret manager for self-hosted testing
- [ ] Correspondence storage, retention, deletion/export, and attachment-limit
  policy before synchronizing real reader mail
- [ ] Replace Mail authorization source-contract assertions with owner/admin/editor
  request-dispatch coverage
- [ ] MIME parsing, attachments, spam handling, and correspondence delivery policy

See [`docs/subsystems/distribution.md`](./subsystems/distribution.md),
[`docs/subsystems/email-client.md`](./subsystems/email-client.md), and
[ADR 0010](./adr/0010-imap-inbox-adapter.md).

## Global Settings ✅

- [x] Canonical `/dashboard/settings` surface
- [x] Settings-only section folio
- [x] Publication, newsletter, discussion, analytics, and appearance defaults
- [x] Section-local status and error feedback
- [x] Non-secret Ready / Disabled / Needs setup states
- [x] Explicit Account and System availability boundaries

See [`docs/subsystems/settings.md`](./subsystems/settings.md).

## Katakata 1.0 — Open-source readiness

- [ ] Separate publication-specific assumptions from reusable platform behavior
- [ ] Define product naming and namespace migration strategy
- [ ] Add installation and first-run publication setup
- [ ] Stabilize unified web and email publishing contracts
- [ ] Define theme and plugin extension APIs
- [ ] Add import/export portability and backup/restore guidance
- [ ] Publish self-hosting and security documentation
- [ ] Stabilize public APIs and 1.0 compatibility guarantees

## Katakata 1.x — Publishing platform

- [ ] Multiple publications
- [ ] Richer author and team model
- [ ] Tags, collections, and series
- [ ] Media library
- [ ] FTS5 search
- [ ] Subscriber segmentation
- [ ] Newsletter templates and web/email preview parity
- [ ] Additional mail and discussion adapters

## Katakata Cloud

- [ ] Managed hosting
- [ ] Domains and TLS
- [ ] Managed email delivery and deliverability
- [ ] Scheduled workers
- [ ] Managed backups
- [ ] Media CDN and optimization
- [ ] Advanced analytics
- [ ] Teams and permissions
- [ ] Hosted integrations

## Operational Index (SQLite)

Goal: accelerate a substantial article corpus without moving canonical content out of Markdown.

- [ ] Define schema and migration policy
- [ ] Add deterministic index build and rebuild commands
- [ ] Add FTS5 full-text search
- [ ] Index article, tag, author, collection, and archive metadata
- [ ] Track incremental build state and source fingerprints
- [ ] Add related-content and backlink indexes
- [ ] Verify that deleting the database loses no canonical content

SQLite is an operational index and cache. It is never the source of truth.

## Analytics Platform (DuckDB)

Goal: support reporting and insights across publication, readership, newsletter, and social engagement data.

- [ ] Define append-only analytics event schema
- [ ] Record publication and readership events
- [ ] Integrate newsletter and discussion engagement events
- [ ] Build Parquet export and compaction pipeline
- [ ] Add DuckDB query service
- [ ] Produce article, author, tag, cadence, and engagement reports
- [ ] Define retention, privacy, and aggregation rules
- [ ] Verify analytics failure never blocks publishing

DuckDB queries analytical datasets. It is never canonical storage and is never required to publish.

---

Each active area has a subsystem document under
[`docs/subsystems/`](./subsystems). Architectural exceptions require an ADR
before implementation.

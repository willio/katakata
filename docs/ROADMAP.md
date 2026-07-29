# Roadmap

The build proceeds in eight phases. Each phase produces a working,
independently useful increment — nothing is scaffolded ahead of when
it's needed.

## Phase 0 — Foundation ✅

- [x] Repository structure
- [x] Bootstrap (`bootstrap/app.php`, shared by HTTP/CLI/tests)
- [x] Configuration (immutable `Config`, loaded from `config/*.php`)
- [x] Routing (minimal `Router`, `routes/web.php`)
- [x] Documentation (this roadmap, ADRs, README)

## Phase 1 — Content Engine ✅ *current*

- [x] Repository (reads `content/` into structured objects)
- [x] Indexing (discovery of posts/drafts/authors/assets)
- [x] Drafts
- [x] Metadata / front matter parsing
- [x] Assets

See [`docs/subsystems/content-engine.md`](./subsystems/content-engine.md)
for details.

## Phase 2 — Rendering

- [ ] Website (typography-first templates)
- [ ] Archives
- [ ] Feeds (RSS, JSON Feed)
- [ ] Typography system

## Phase 3 — Editorial

- [ ] Editor (distraction-free, keyboard-first, Markdown-native)
- [ ] Revisions
- [ ] Scheduling
- [ ] Publishing pipeline

## Phase 4 — Distribution

- [ ] Newsletter
- [ ] Threads adapter
- [ ] Webhooks
- [ ] Insights / engagement metadata

## Phase 5 — Extensibility

- [ ] Plugins
- [ ] Themes
- [ ] Public APIs
- [ ] Additional publisher adapters (Mastodon, Bluesky, LinkedIn, etc.)

## Phase 6 — Operational Index (SQLite)

Goal: accelerate a substantial article corpus without moving canonical content out of Markdown.

- [ ] Define SQLite schema and migration policy
- [ ] Add deterministic index build and rebuild commands
- [ ] Add FTS5 full-text search
- [ ] Index article, tag, author, collection, and archive metadata
- [ ] Track incremental build state and source fingerprints
- [ ] Add related-content and backlink indexes
- [ ] Define optional operational state for sessions and queues
- [ ] Verify that deleting the database loses no canonical content

Planned structure:

```text
app/Index/
storage/index/katakata.sqlite
```

SQLite is an operational index and cache. It is never the source of truth.

## Phase 7 — Analytics Platform (DuckDB)

Goal: support reporting and insights across publication, readership, newsletter, and social engagement data.

- [ ] Define append-only analytics event schema
- [ ] Record publication and readership events
- [ ] Integrate newsletter and Threads engagement events
- [ ] Build Parquet export and compaction pipeline
- [ ] Add DuckDB query service
- [ ] Produce article, author, tag, cadence, and engagement reports
- [ ] Add dashboard-ready datasets
- [ ] Define retention, privacy, and aggregation rules
- [ ] Verify that analytics failure never blocks publishing

Planned structure:

```text
app/Analytics/
storage/analytics/events/
storage/analytics/parquet/
```

DuckDB queries analytical datasets. It is never canonical storage and is never required to publish.

---

Each phase gets its own subsystem document under
[`docs/subsystems/`](./subsystems) once work on it begins.

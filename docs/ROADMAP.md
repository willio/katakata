# Roadmap

The build proceeds in six phases. Each phase produces a working,
independently useful increment — nothing is scaffolded ahead of when
it's needed.

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
- [x] Chronological archive
- [x] Author archives
- [x] Feeds (RSS, JSON Feed)
- [x] Typography system

See [`docs/subsystems/rendering.md`](./subsystems/rendering.md).

## Phase 3 — Editorial

- [ ] Editor
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
- [ ] Additional publisher adapters

---

Each phase gets its own subsystem document under
[`docs/subsystems/`](./subsystems) once work on it begins.

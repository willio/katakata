# Roadmap

The build proceeds in six phases. Each phase produces a working,
independently useful increment — nothing is scaffolded ahead of when
it's needed.

## Phase 0 — Foundation ✅ *current*

- [x] Repository structure
- [x] Bootstrap (`bootstrap/app.php`, shared by HTTP/CLI/tests)
- [x] Configuration (immutable `Config`, loaded from `config/*.php`)
- [x] Routing (minimal `Router`, `routes/web.php`)
- [x] Documentation (this roadmap, ADRs, README)

## Phase 1 — Content Engine

- [ ] Repository (reads `content/` into structured objects)
- [ ] Indexing (discovery of posts/drafts/authors/assets)
- [ ] Drafts
- [ ] Metadata / front matter parsing
- [ ] Assets

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

---

Each phase gets its own subsystem document under
[`docs/subsystems/`](./subsystems) once work on it begins.

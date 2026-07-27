# Roadmap

The build proceeds in six phases. Each phase produces a working,
independently useful increment — nothing is scaffolded ahead of when
it's needed.

## Phase 0 — Foundation ✅

- [x] Repository structure
- [x] Bootstrap (`bootstrap/app.php`, shared by HTTP/CLI/tests)
- [x] Configuration (immutable `Config`, loaded from `config/*.php`)
- [x] Routing (minimal `Router`, `routes/web.php`)
- [x] Documentation (this roadmap, ADRs, README)

## Phase 1 — Content Engine ✅

- [x] Repository (reads `content/` into structured objects)
- [x] Indexing (discovery of posts/drafts/authors/assets)
- [x] Drafts
- [x] Metadata / front matter parsing
- [x] Assets

See [`docs/subsystems/content-engine.md`](./subsystems/content-engine.md)
for details.

## Phase 2 — Rendering *current*

- [ ] Website *(plain PHP view foundation implemented; article views pending)*
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

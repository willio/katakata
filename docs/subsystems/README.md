# Subsystem Specifications

Subsystem documents explain **how** a part of Katakata works, in
detail — as opposed to ADRs, which explain **why** a decision was
made, or the Master Specification, which defines the overall vision.

Per the Master Specification's "Calm software" principle — every
feature (and every document) must justify its existence — subsystem
specs are written when the subsystem they describe actually exists,
not ahead of time.

| Subsystem | Introduced in | Doc |
|---|---|---|
| Content Engine (Repository, Discovery, Front Matter) | Phase 1 | [content-engine.md](./content-engine.md) |
| Legacy Document Import | Phase 3 | [import.md](./import.md) |
| Renderer (Website, Archives, Feeds, Typography) | Phase 2 | — |
| Editorial (Editor, Revisions, Scheduling, Publishing) | Phase 3 | — |
| Distribution (Newsletter, Threads Adapter, Webhooks) | Phase 4 | — |
| Extensibility (Plugins, Themes, APIs) | Phase 5 | — |

See [`../ROADMAP.md`](../ROADMAP.md) for the full phase plan.

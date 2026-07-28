# Subsystem: Dashboard (Owner's View)

Phase: 4 (Dashboard and Analytics)\n\nStatus: content-backed shell, SEO status, visit trend, and recent visits implemented

## Purpose

The Dashboard is the editor's (owner's) home view after login: a
single screen answering "what's happening" and "what do I do next" —
without becoming a second content-management surface competing with
the Editor itself. Per the Master Specification's "Calm software"
principle, every card on this screen must justify its presence; this
is not a place to accumulate widgets.

## Remaining dependency

ADR 0009 and `docs/subsystems/analytics.md` now define the SQLite store,
privacy-bounded recording, summary queries, and retention. The dashboard consumes `AnalyticsSummary` through a failure-isolated presenter without knowing how it is stored.

IP-to-region derivation remains deliberately unresolved. The visitor map
must wait until its source, precision, disclosure copy, and retention
implications are approved. Region fields therefore remain nullable.

## Layout

Single scrollable column, in this order. No sidebar navigation
competing with content on this screen — top-level nav (Dashboard /
New Post / Settings) is a slim persistent header, nothing more.

```
┌─────────────────────────────────────────────┐
│  Katakata            [New Post]  [Settings] │  ← header, persistent
├─────────────────────────────────────────────┤
│  [Stat card] [Stat card] [Stat card] [Stat]  │  ← row 1
├─────────────────────────────────────────────┤
│  Visitor map              │  Recent visits   │  ← row 2, two columns
├─────────────────────────────────────────────┤
│  The Buzz (Threads replies)                  │  ← row 3
├───────────────────────┬───────────────────────┤
│  Recent Drafts         │  Top Posts            │  ← row 4, two columns
└───────────────────────┴───────────────────────┘
```

### Row 1 — Stat cards

Small, sans-serif, numbers-first. Four cards, no more without a
stated reason (per "calm software" — a stat card is a permanent
commitment to computing and displaying that number forever):

| Card | Content | Source |
|---|---|---|
| Visitors (7d) | Total + trend vs. prior 7d | `AnalyticsSummary` (SQLite, ADR 0009) |
| Published posts | Total count | `Repository::posts()` — exists today |
| Drafts in progress | Total count | `Repository::drafts()` — exists today |
| Threads replies (7d) | Total across synced discussions | Threads sync metadata (ADR 0004) |

Each card: a single large number, a one-line label, an optional small
trend indicator (▲/▼ + %, sans, muted color — not a full sparkline
chart at this size; that's decoration, not information, on a card
this small).

### Row 2 — Visitor map + recent visits

Two columns on desktop, stacked on narrow viewports.

- **Visitor map:** a world/region map, dots or heat regions sized by
  visit count. Consumes `AnalyticsSummary.regions`, but remains hidden until region
  derivation and disclosure copy are approved.
- **Recent visits:** a plain log table — timestamp, page (article
  title, not raw path), referrer if available, country/region (not
  precise IP). Last 10–20 entries, "view all" link if a fuller log
  view is ever justified — not built by default.

**Explicitly not included:** individual visitor identity, raw IP
addresses displayed in the UI, session replay, or anything beyond
aggregate/anonymized counts. This isn't a legal requirement I can
confirm for you — verify against whatever privacy regulations apply
in your jurisdiction and your readers' — but it's the minimum bar
consistent with "secure defaults."

### Row 3 — The Buzz

**Read-only Threads replies, synced via ADR 0004's mechanism.** Not
comments, not a reply box, not moderation tools beyond a link out to
Threads itself — per the Master Specification: "Conversation
moderation belongs to Threads."

Layout: a compact list — avatar (sans, small), reply excerpt (1–2
lines, sans — this is UI chrome, not article content, so serif
doesn't apply here), which article it's attached to, relative
timestamp, link to view the full thread. 5–8 most recent replies
across all articles, newest first.

If Threads sync is unavailable (per ADR 0004's failure model), this
section shows a quiet "Threads sync unavailable" state — never a
blank gap that looks broken, and never blocks the rest of the
Dashboard from rendering.

### Row 4 — Recent Drafts + Top Posts

Two columns on desktop, stacked on narrow viewports.

- **Recent Drafts:** title, relative "last edited" time, a direct
  link into the Editor for that draft. 5 most recent by
  `updated_at` — backed by `Repository::drafts()`, which exists
  today. Empty state: a quiet prompt toward the New Post CTA, not an
  empty box.
- **Top Posts:** defaults to **latest published**, not "most
  visited" — per your spec, "defaults to latest." Analytics can later support a toggle (Latest / Most visited),
  but the default view must never require analytics to be configured
  to show something useful. Backed by `Repository::posts()` today for
  the default state.

## Header actions

- **"New Post"** — a single CTA, opens the Editor (per `DESIGN.md`'s
  writing experience: fullscreen, chrome hidden by default) with a
  new, unsaved draft. This is the only "create" affordance on the
  Dashboard — no secondary "quick draft" or "import" buttons cluttering
  the header.
- **"Settings"** — opens the *account/site* settings, scoped
  explicitly as follows.

## Settings — scope boundary

This is the one place scope needs to be drawn precisely, because your
spec draws a clean line and it's worth stating explicitly so it
doesn't blur during implementation:

| Belongs to global Settings | Belongs to the Editor (per-post) |
|---|---|
| Account email / password | Post title, slug, tags, excerpt |
| Author profile (bio, avatar) — per `Author` object | Post status (draft/published), publish date |
| Author invites (multi-author, per Master Spec) | Post-specific Threads publication toggle |
| Threads account connection (per-author identity, ADR 0004) | — |
| Site-wide config (site name, tagline — `config/app.php`-level) | — |
| Newsletter service connection (Phase 5) | — |

Global Settings never edits post content or per-post metadata.
Per-post settings never touches account/site-wide configuration. If a
future field is ambiguous (e.g. "default Threads visibility for new
posts"), it belongs in global Settings as a *default*, with the
per-post Editor able to override it for that one post — global
settings set defaults, they don't reach into individual posts.

## Data contracts

`AnalyticsSummary` and `RecentVisit` now exist under `app/Analytics/`.
The remaining shapes stay explicit so later slices do not infer them from
layout prose:

```
AnalyticsSummary {
    visits7d: int
    visits30d: int
    visits365d: int
    visitsAllTime: int
    visits7dTrendPct: float
    regions: array<{region: string, count: int}>
    recentVisits: array<{at: DateTimeImmutable, page: string, referrer: ?string, region: ?string}>
}

SeoCheckSummary {
    checkedAt: DateTimeImmutable
    issues: array<{slug: string, type: string, message: string}>
}

ThreadsReply {
    postSlug: string
    author: string
    avatarUrl: ?string
    excerpt: string
    at: DateTimeImmutable
    threadUrl: string
}
```

`AnalyticsSummary` and its windowed counts are backed by
`storage/analytics/analytics.sqlite`, per ADR 0009. Note the "visits"
naming (not "unique visitors") is deliberate — see that ADR's honest
accounting of what the daily-rotating visitor hash can and can't
guarantee across multi-day windows; the Dashboard's stat cards should
use the same careful language, not silently promise more precision
than the underlying counts support.

`SeoCheckSummary` is implemented under `app/Seo/`, exposed by `seo:check`, and computed fresh from `Repository::posts()` per
ADR 0009's "basic SEO monitoring" scope — reproducible, cacheable,
never persisted as historical data.

`ThreadsReply` is now backed by the rebuildable Threads reply cache. The
Dashboard reads it through a failure-isolated presenter: disabled Threads or
unreadable generated state produces a quiet unavailable message, while an
available empty cache produces a distinct no-replies state. Refresh explicitly
with `php bin/katakata threads:sync`; the Dashboard never calls the provider
during a page request.

## What's deliberately not here

- Comment moderation tools — doesn't exist, per ADR 0004; Threads
  handles moderation.
- Multi-dashboard / per-author dashboards — single-owner view for now;
  revisit if multi-author usage (Master Spec's "Multi-Author"
  section) shows a real need for author-scoped views.
- Customizable/reorderable dashboard widgets — fixed layout, on
  purpose; configurability here is complexity spent on a screen that
  should answer "what's happening" at a glance, not become its own
  settings surface.
- Real-time updates (websockets/polling) — the Dashboard reflects
  state as of page load; a manual refresh is enough until a concrete
  need for live updates is demonstrated.

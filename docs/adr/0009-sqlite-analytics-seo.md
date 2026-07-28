# ADR 0009: SQLite for Analytics and Basic SEO Monitoring

## Status

Accepted

## Context

The Dashboard subsystem (`docs/subsystems/dashboard.md`) needs
windowed unique-visitor counts (7d / 30d / 365d / all-time) and basic
SEO monitoring. Neither fits the existing architecture cleanly:

- **Unique-visitor counts over rolling windows** require queries like
  "distinct visitor identifiers in the last N days," repeated at
  several window sizes, on every dashboard load. A flat append-only
  log file (the option floated previously) would require re-parsing
  and re-deduplicating the entire log on every request to answer
  that — cheap at first, increasingly expensive as history grows, and
  the dashboard is a page real people load repeatedly, not a batch
  job.
- **Katakata has no database of record** (ADR 0001) and is
  dependency-light by default (ADR 0002). Introducing a client-server
  database (Postgres, MySQL) purely for visit counts would be a large
  operational cost for a small, well-understood query need.

SQLite resolves the query problem without the operational cost: it's
a single file, requires no server process, and PHP's `pdo_sqlite`
extension is part of the standard PHP distribution (not a Composer
dependency, consistent with ADR 0002's "runs without `composer
install`" guarantee) — verify `pdo_sqlite` is enabled in the target
deployment's PHP build before relying on this; it's common but not
universal on minimal PHP installs.

This is a deliberate, narrow exception to ADR 0001's "no database of
record," scoped specifically to **observational data that isn't
content** — visit logs are not Markdown, not authored, and not
subject to "files are authoritative." ADR 0001 remains unchanged for
everything it actually governs: posts, drafts, authors, assets.

## Decision

### Storage

A single SQLite database at `storage/analytics/analytics.sqlite`
(new directory, `.gitignore`'d like the rest of `storage/`, with a
`.gitkeep`). One file, two tables:

```sql
CREATE TABLE visits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    path TEXT NOT NULL,
    referrer TEXT,
    region TEXT,
    visitor_hash TEXT NOT NULL,
    created_at TEXT NOT NULL  -- ISO 8601, UTC
);
CREATE INDEX idx_visits_created_at ON visits (created_at);
CREATE INDEX idx_visits_visitor_hash ON visits (visitor_hash);
```

SEO check results are **not** stored here — see "SEO monitoring,"
below, for why.

### Unique-visitor identification

`visitor_hash` is computed per request as:

```
sha256(daily_salt . ip_address . user_agent)
```

truncated to, e.g., 16 hex characters. `daily_salt` rotates every 24
hours (derived from the UTC date, plus a fixed application-level
secret from `.env` — never committed). Consequences of this design,
stated plainly:

- **Raw IP addresses are never persisted** — only a salted, truncated
  hash. This is a meaningful privacy improvement over storing IPs
  directly, but it is not full anonymization; treat it as
  pseudonymous data, not anonymous data, when deciding retention and
  disclosure policy.
- Because the salt rotates daily, the same visitor gets a
  **different** hash on different days. This means a cross-day "unique visitors" count
  within a single day is accurate, but **"unique" across a 7/30/365-day
  window overcounts true unique humans** — a person visiting on day
  1 and day 5 counts as two "uniques" in a 7-day window, since their
  hash differs each day. This is the honest tradeoff for not tracking
  people persistently. If you need true cross-day unique tracking,
  that requires a persistent (non-rotating) identifier, which is a
  materially different privacy posture — a decision to make
  explicitly later, not a default to slide into now.
- Given the above, **rename the metric in the UI** from "unique
  visitors" to something accurate, e.g. "daily active visits,
  deduplicated" or simply keep "visits" and drop "unique" framing
  where the window exceeds one day. Precise language here matters
  more than it looks like it does.

### Retention

Rows older than 400 days are deleted by a scheduled/manual command
(`php bin/katakata analytics:prune`, mirroring the existing
`content:validate` pattern). 400 days (not 365) so a full year is
always queryable even a day before a prune run. This number is a
starting proposal, not a hard requirement — set it based on your own
data retention comfort.

### Query pattern

```sql
SELECT COUNT(DISTINCT visitor_hash) FROM visits WHERE created_at >= ?;
```

run once per window (7d / 30d / 365d), plus one unconditioned count
for all-time. Four cheap indexed queries per dashboard load — this is
the entire performance case for SQLite over log parsing.

### SEO monitoring — basic scope

Deliberately narrow, and deliberately **not** stored in SQLite,
because every check below is fully reproducible from
`Repository::posts()` — per ADR 0003, reproducible-from-content data
belongs in a cache, not a store you'd ever back up:

| Check | Source |
|---|---|
| Missing or empty `title` | Already required by `Repository` — this would only ever catch a future schema regression |
| Missing/short/long `excerpt` (meta description proxy) | `Post::$excerpt`, flag if null or outside ~50–160 chars |
| Duplicate `slug` across posts | Computed across `Repository::posts()->all()` |
| Broken internal links | Parse `Post::$body` for `/YYYY/MM/slug`-shaped links, check each resolves via `Repository::findPost()` |
| `sitemap.xml` / `robots.txt` presence | Filesystem check under `public/` — depends on Phase 2's Renderer having generated a sitemap at all |

Explicitly **out of scope** for "basic": Google Search Console
integration, rank tracking, backlink monitoring, Core Web Vitals,
crawling external pages. Any of these is a legitimate future ADR, not
an assumed extension of this one — this is the same distinction drawn
against `crawlseo` previously: that's a different tool solving a
different problem.

Results surface via a `content:validate`-style CLI command
(`php bin/katakata seo:check`) and/or a lightweight cached summary on
the Dashboard — computed fresh or from a short-lived cache, never
persisted as if it were historical data, since re-running it should
always produce the same result for the same content.

## Consequences

- **New, non-reproducible data enters the project for the first
  time.** Every other piece of "storage" in Katakata is either
  canonical content (backed up by the writer via their own tools, per
  ADR 0001) or disposable/regenerable (caches, indexes). `visits` is
  neither — if `analytics.sqlite` is lost, that history is gone
  permanently, and there's no Markdown to regenerate it from. If
  visit history matters to you, back up this one file; nothing else
  in the architecture assumes you need to.
- `pdo_sqlite` becomes a soft runtime requirement. It's standard, but
  confirm it's present wherever this deploys (per ADR 0002's nginx +
  Apache/Dewaweb targets) before relying on it — this is one line to
  check (`php -m | grep sqlite`) and cheap to get wrong silently.
- The "unique visitors" metric has a real, stated accuracy limitation
  (daily-rotating hash) that must be reflected honestly in the
  Dashboard's copy, not smoothed over with confident-sounding UI.
- SEO checks stay consistent with the rest of the architecture
  (reproducible, disposable, content-derived) — they do not inherit
  any of the backup/retention concerns above.
- This ADR does not cover deployment/migration of the SQLite schema
  itself (e.g. what happens on schema changes to `visits`). At this
  size, a hand-written `CREATE TABLE IF NOT EXISTS` at boot is
  sufficient; a real migration system is unjustified complexity until
  the schema actually needs to evolve more than once or twice.

# Subsystem: Dashboard (Owner's View)

Phase: 4 (Dashboard and Analytics)

Status: linked attention dashboard implemented

## Purpose

The Dashboard is the owner's home view after login: one calm screen answering
"what's happening" and "what do I do next" without becoming a competing
content-management surface.

## Header contract

The dashboard-style header contains only:

- publication identity;
- a separate **New post** action;
- global **Settings**.

It does not introduce persistent application navigation.

## Attention cards

The dashboard exposes exactly four linked cards through
`DashboardAttention::cards()`:

| Card | Destination | Meaning |
| --- | --- | --- |
| Visits | `/analytics` | Privacy-bounded readership summary. |
| Posts | `/posts` | Published editorial content. |
| Drafts | `/posts?status=drafts` | Work in progress. |
| Inbox | `/mail` | Combined reader-message and campaign attention. |

SEO is deliberately not a dashboard card. Reproducible SEO and content
warnings belong with analytics diagnostics.

The Inbox card consumes `MailAttention::summary()` and displays the combined
new-item count plus precise split copy, for example:

```text
2 reader messages · 1 campaign needs attention
```

If nothing requires attention, it shows zero with `No mail needs attention`.
Mailbox unavailability must not block the rest of the dashboard.

## Content index

`/posts` is the canonical read-first editorial index. It accepts:

```text
/posts?status=all|drafts|scheduled|published
```

Unknown status values fall back to `all`. The surface shows stable filters, a
separate New post action, status/author/date metadata, and Edit or View actions.
Inline editing is deliberately excluded.

Legacy `/editor` redirects to `/posts`, while `/editor/new` and direct draft
editor URLs remain stable.

## Analytics

`/analytics` is authenticated and failure-isolated. It reports the available
visit summary and recent visits without exposing raw IP addresses. Analytics
failure never blocks dashboard or publishing work.

IP-to-region derivation remains unresolved. Regional aggregates and the
visitor map stay deferred until source, precision, disclosure, and retention
requirements are approved.

## Recent content and activity

The dashboard retains:

- five most recently updated drafts with direct editor links;
- five latest published posts;
- recent privacy-bounded visits;
- failure-isolated read-only discussion activity.

Optional discussion or analytics state may be unavailable without degrading
the rest of the page.

## Data contracts

```text
DashboardAttention::cards(): list<{
    label: string,
    count: int|string,
    detail: ?string,
    href: string
}>

AnalyticsSummary {
    visits7d: int
    visits30d: int
    visits365d: int
    visitsAllTime: int
    visits7dTrendPct: float
    regions: array<{region: string, count: int}>
    recentVisits: array<{
        at: DateTimeImmutable,
        page: string,
        referrer: ?string,
        region: ?string
    }>
}

MailAttention::summary(): {
    reader: int,
    campaigns: int,
    total: int,
    detail: string
}
```

## Privacy boundary

The dashboard never reads reader correspondence content directly. It consumes
only the Mail attention summary. Reader messages, attachments, and drafts remain
private operational data outside Git, public roots, analytics, and diagnostic
logs.

## Deliberately excluded

- Raw visitor identity or IP display.
- Comment moderation tools.
- Reply composition from the dashboard.
- Customizable/reorderable widgets.
- Real-time polling or websockets.
- Request-time calls to analytics, discussion, IMAP, or other external providers.

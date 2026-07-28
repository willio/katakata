# Subsystem: Analytics

Phase: 4 (Dashboard and Analytics)

## Purpose

Analytics records enough observational data to answer the owner's basic
traffic questions without storing raw IP addresses or introducing a remote
tracking service. It is the narrow SQLite exception accepted by ADR 0009;
Markdown remains authoritative for all authored content.

## Runtime

`AnalyticsStore` owns `storage/analytics/analytics.sqlite`. The schema is
created lazily on the first record or query, so a missing `pdo_sqlite`
extension never prevents a reader page from rendering. Run
`php bin/katakata analytics:check` during deployment to fail explicitly
when the extension or analytics secret is missing.

Configure either `ANALYTICS_SECRET` or the existing `APP_KEY`. The secret
is combined with UTC date, remote address, and user agent before SHA-256
hashing; only the first 16 hexadecimal characters are persisted. Raw IP
addresses and user-agent strings are never written to SQLite.

Only `REMOTE_ADDR` is used. Proxy forwarding headers are not trusted until
the deployment's trusted-proxy boundary is specified.

## Recording

`VisitRecorder` is failure-isolated: missing configuration, unavailable
SQLite, or a write failure returns `false` and does not break the public
response. HTML reader routes record after their content has resolved
successfully. Feeds, authentication, editor, health, and missing pages are
not counted.

Region is nullable. Phase 4 must not infer it until the geolocation source,
precision, and disclosure copy are approved.

## Queries

`AnalyticsStore::summary()` returns:

- deduplicated 7-, 30-, and 365-day counts;
- all-time deduplicated daily visitor hashes;
- 7-day trend versus the previous 7 days;
- 30-day regional totals when region data exists;
- the latest visits.

Because hashes rotate daily, multi-day counts can overcount people who
return on different days. Dashboard copy must say visits, never promise
persistent unique visitors.

## Retention

`php bin/katakata analytics:prune` deletes rows older than the configured
retention, 400 days by default. The database is non-reproducible operational
history and must be backed up separately if retaining it matters.

## Deferred

- IP-to-region derivation and visitor map
- bot classification
- persistent cross-day identity
- external analytics providers
- session replay or individual visitor profiles

# Kata-kata

A calm, typography-first publishing platform for the web and inbox.

Markdown is canonical. Everything else — HTML, RSS, JSON Feed, search
indexes, newsletters, and provider adapters — is generated or operational
state.

Katakata is dogfooded in production by [Kamantara](https://kamantara.com), a
private editorial publication. Kamantara's editorial content, mailbox data,
and deployment state are intentionally kept outside this public repository.


> **Write once. Own forever. Publish anywhere.**

See [`docs/MASTER_SPECIFICATION.md`](docs/MASTER_SPECIFICATION.md) for
the full vision, philosophy, and architecture. This README covers only
what's needed to run what exists today.

## Status: Dashboard, settings, and Mail workspace implemented

The authenticated owner experience now includes:

- a sparse dashboard with linked Visits, Posts, Drafts, and Inbox cards;
- a filtered content index at `/posts`;
- analytics at `/analytics`;
- a global settings control desk at `/dashboard/settings`;
- one `/mail` workspace for reader correspondence readiness and newsletter
  campaign work.

The inbox boundary is provider-neutral. IMAP is the first planned adapter, but
request-time IMAP access is prohibited. Until a scheduled sync adapter is
configured, Inbox shows a non-secret setup state while campaign work remains
available.

## Requirements

- PHP 8.5 or later with OpenSSL and PDO SQLite
- Composer (optional — only needed to run the test suite via PHPUnit)

## Quickstart

```bash
cp .env.example .env
php bin/katakata serve
# or: php -S 127.0.0.1:8000 -t public
```

Visit `http://127.0.0.1:8000/` for the homepage,
`http://127.0.0.1:8000/healthz` for the health check, or a post's
canonical `/{year}/{month}/{slug}` URL. The complete published archive is
available at `/archive`; the authenticated owner dashboard is at `/dashboard`;
content management is at `/posts`; analytics is at `/analytics`; global
settings are at `/dashboard/settings`; Mail is at `/mail`; feeds are available
at `/feed.xml` and `/feed.json`; and author archives are at `/authors/{slug}`.

For a complete installation walkthrough, see
[`docs/operations/self-hosting.md`](docs/operations/self-hosting.md).

### Local nginx HTTPS

`config/nginx/katakata.conf` serves `katakata.local` over HTTPS and redirects
HTTP to HTTPS. Create the certificate and private key locally at
`config/nginx/ssl/katakata.local.crt` and
`config/nginx/ssl/katakata.local.key`; they are deliberately ignored and must
never be committed. When testing an isolated worktree, use a local vhost copy
whose `root` points at that worktree's `public/` directory rather than editing
the tracked configuration.

## CLI

```bash
php bin/katakata about
php bin/katakata routes:list
php bin/katakata serve [host]
php bin/katakata content:list
php bin/katakata content:validate
php bin/katakata import:document <path> [--author=name] [--dry-run]
php bin/katakata import:directory <path> [--recursive] [--author=name] [--dry-run]
php bin/katakata draft:create <slug> <title>
php bin/katakata draft:edit <slug>
php bin/katakata draft:schedule <slug> <ISO-8601>
php bin/katakata draft:publish <slug> [ISO-8601]
php bin/katakata publish:due
php bin/katakata revisions:list <slug>
php bin/katakata auth:owner <email> <password>
php bin/katakata auth:invite <email> [admin|editor]
php bin/katakata distribution:publish <post-slug> [newsletter]
php bin/katakata newsletter:dispatch <post-slug>
php bin/katakata mail:work [limit]
php bin/katakata resend:webhooks:check
php bin/katakata threads:sync
php bin/katakata analytics:check
php bin/katakata analytics:prune
php bin/katakata seo:check
```

## Tests

```bash
composer install
composer test
# or: phpunit
```

Set `ANALYTICS_SECRET` (or `APP_KEY`) in `.env`, then run
`php bin/katakata analytics:check` during deployment. Visit recording is
failure-isolated and never stores raw IP addresses.

The application itself never requires Composer's autoloader to run.

Document import requires PHP's DOM and ZIP extensions. Importing legacy `.doc`
files additionally requires LibreOffice (`soffice` or `libreoffice`) on `PATH`.
See [`docs/subsystems/import.md`](docs/subsystems/import.md) for reconciliation,
metadata, dry-run, and collision behavior.

## Production identity and email

The canonical production origin is `https://katakata.example`; the default
administrative and sender address is `admin@katakata.example`. Production email
uses a named transport selected by `MAIL_TRANSPORT`, with Resend as the initial
provider and the filesystem driver retained for development.

See the complete [mail transport and Resend setup guide](docs/subsystems/distribution.md#resend-production-setup-for-katakatacom).

Reader correspondence is separate from campaign delivery. It is private
operational data, never canonical content, and remains outside Git, public
roots, analytics, and diagnostic logs. See
[`docs/subsystems/email-client.md`](docs/subsystems/email-client.md).

## Repository Structure

```
app/         Application code
bin/         CLI entrypoint
bootstrap/   Shared bootstrap
config/      Immutable configuration
content/     Canonical Markdown content
docs/        Specifications, ADRs, and subsystem docs
public/      Web document root
resources/   Plain PHP views and assets
routes/      Route definitions
storage/     Reproducible runtime files
tests/       PHPUnit suite and fixtures
```

The `content/` folder ships with example content. Only `public/` is
web-accessible.

## Design Principles

- [ADR 0001 — Plain Markdown Storage](docs/adr/0001-plain-markdown-storage.md)
- [ADR 0002 — PHP Runtime](docs/adr/0002-php-runtime.md)
- [ADR 0003 — Static-first Architecture](docs/adr/0003-static-first-architecture.md)
- [ADR 0004 — Threads Discussion Layer](docs/adr/0004-threads-discussion-layer.md)
- [ADR 0005 — Minimal Front Matter Parser](docs/adr/0005-minimal-front-matter-parser.md)
- [ADR 0006 — Plain PHP Views](docs/adr/0006-plain-php-views.md)
- [ADR 0007 — Filesystem Editorial Transactions](docs/adr/0007-filesystem-editorial-transactions.md)
- [ADR 0008 — Invite-only Authentication](docs/adr/0008-invite-only-authentication.md)
- [ADR 0009 — SQLite Analytics and SEO](docs/adr/0009-sqlite-analytics-seo.md)
- [ADR 0010 — IMAP Inbox Adapter](docs/adr/0010-imap-inbox-adapter.md)

## Non-Negotiable Rules

- Markdown is canonical.
- Files are authoritative.
- Generated artifacts are disposable.
- Writers own their content.
- Configuration is immutable after boot.
- Controllers remain thin; business logic is framework-independent.
- Every subsystem has a single responsibility.
- Every feature must justify its complexity.

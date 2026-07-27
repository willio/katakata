# Katakata

A calm, typography-first publishing platform built around plain
Markdown files.

Markdown is canonical. Everything else — HTML, RSS, JSON Feed, search
indexes, newsletters, Threads posts — is generated and disposable.

> **Write once. Own forever. Publish anywhere.**

See [`docs/MASTER_SPECIFICATION.md`](docs/MASTER_SPECIFICATION.md) for
the full vision, philosophy, and architecture. This README covers only
what's needed to run what exists today.

## Status: Phase 3 — Editorial complete

Phases 0–3 are complete. In addition to rendering, Katakata now has a
local filesystem editor, canonical revision history, scheduled drafts,
and an atomic publishing pipeline. See [`docs/ROADMAP.md`](docs/ROADMAP.md)
and [`docs/subsystems/editorial.md`](docs/subsystems/editorial.md).

## Requirements

- PHP 8.2 or later
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
available at `/archive`; feeds are available at `/feed.xml` and `/feed.json`,
and author archives at `/authors/{slug}`.

## CLI

```bash
php bin/katakata about
php bin/katakata routes:list
php bin/katakata serve [host]
php bin/katakata content:list
php bin/katakata content:validate
php bin/katakata draft:create <slug> <title>
php bin/katakata draft:edit <slug>
php bin/katakata draft:schedule <slug> <ISO-8601>
php bin/katakata draft:publish <slug> [ISO-8601]
php bin/katakata publish:due
php bin/katakata revisions:list <slug>
```

## Tests

```bash
composer install
composer test
# or: vendor/bin/phpunit
```

The application itself never requires Composer's autoloader to run.

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

## Non-Negotiable Rules

- Markdown is canonical.
- Files are authoritative.
- Generated artifacts are disposable.
- Writers own their content.
- Configuration is immutable after boot.
- Controllers remain thin; business logic is framework-independent.
- Every subsystem has a single responsibility.
- Every feature must justify its complexity.

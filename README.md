# Katakata

A calm, typography-first publishing platform built around plain
Markdown files.

Markdown is canonical. Everything else — HTML, RSS, JSON Feed, search
indexes, newsletters, Threads posts — is generated and disposable.

> **Write once. Own forever. Publish anywhere.**

See [`docs/MASTER_SPECIFICATION.md`](docs/MASTER_SPECIFICATION.md) for
the full vision, philosophy, and architecture. This README covers only
what's needed to run what exists today.

## Status: Phase 2 — Rendering

Phase 0 (Foundation) and Phase 1 (Content Engine) are done: the
application bootstrap, routing, and a Repository that reads
`content/` — posts, drafts, authors, assets — into structured objects.
Phase 2 has begun with dependency-free plain PHP views and an
output-escaping helper. The homepage now renders through the View
service; article rendering, archives, feeds, and the typography system
remain. See
[`docs/ROADMAP.md`](docs/ROADMAP.md) for the full phase plan and
[`docs/subsystems/content-engine.md`](docs/subsystems/content-engine.md)
for how the Content Engine works.

## Requirements

- PHP 8.2 or later
- Composer (optional — only needed to run the test suite via PHPUnit)

## Quickstart

```bash
cp .env.example .env

# Serve the site (defaults to http://127.0.0.1:8000)
php bin/katakata serve

# Or use PHP's built-in server directly
php -S 127.0.0.1:8000 -t public
```

Visit `http://127.0.0.1:8000/` for the homepage, or
`http://127.0.0.1:8000/healthz` for a JSON health check.

## CLI

```bash
php bin/katakata about             # Print app name and tagline
php bin/katakata routes:list       # List registered routes
php bin/katakata serve [host]      # Serve via PHP's built-in server
php bin/katakata content:list      # List posts, drafts, authors, and assets
php bin/katakata content:validate  # Validate all content; exit 1 if anything fails
```

## Tests

```bash
composer install   # only needed the first time, to fetch PHPUnit
composer test
# or: vendor/bin/phpunit
```

The application itself never requires Composer's autoloader to run —
only the test suite does, via PHPUnit as a dev dependency.

## Repository Structure

```
app/         Application code (Kernel, Container, Config, Http, Console, Content)
bin/         CLI entrypoint (bin/katakata)
bootstrap/   Shared bootstrap for HTTP, CLI, workers, and tests
config/      Configuration files (loaded once at boot, then frozen)
content/     Canonical Markdown content (posts, drafts, authors, assets)
docs/        Master Specification, Roadmap, ADRs, subsystem specs
public/      The only web-accessible directory (public/index.php)
resources/   Views and other non-code assets (Phase 2+)
routes/      Route definitions
storage/     Logs, cache, framework files
tests/       PHPUnit test suite (plus tests/Fixtures/ for content fixtures)
```

The `content/` folder ships with a couple of example posts, an
author, and a draft so `content:list` has something to show out of
the box — replace or delete them as you start writing for real.

Only `public/` is web-accessible; everything else should sit outside
the webserver's document root in a real deployment.

## Design Principles

The accepted ADRs explain the foundational decisions behind what's here:

- [ADR 0001 — Plain Markdown Storage](docs/adr/0001-plain-markdown-storage.md)
- [ADR 0002 — PHP Runtime](docs/adr/0002-php-runtime.md)
- [ADR 0003 — Static-first Architecture](docs/adr/0003-static-first-architecture.md)
- [ADR 0004 — Threads Discussion Layer](docs/adr/0004-threads-discussion-layer.md) *(architecture only — no Threads adapter exists yet; that's Phase 4)*
- [ADR 0005 — Minimal Front Matter Parser](docs/adr/0005-minimal-front-matter-parser.md)
- [ADR 0006 — Plain PHP Views](docs/adr/0006-plain-php-views.md)

## Non-Negotiable Rules

- Markdown is canonical.
- Files are authoritative.
- Generated artifacts are disposable.
- Writers own their content.
- Configuration is immutable after boot.
- Controllers remain thin; business logic is framework-independent.
- Every subsystem has a single responsibility.
- Every feature must justify its complexity.

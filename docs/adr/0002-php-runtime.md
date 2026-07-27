# ADR 0002: PHP Runtime

## Status

Accepted

## Context

Katakata needs a runtime that is easy to deploy anywhere, has broad
and cheap hosting availability, supports "progressive enhancement"
(a website that works without JavaScript), and doesn't force writers
or maintainers into a heavyweight operational stack for what is, at
its core, a file-based publishing tool.

## Decision

Katakata runs on PHP (8.2+), via a small, hand-rolled application
kernel rather than a full-stack framework (e.g. Laravel, Symfony).

The kernel (`app/Application.php`) is deliberately minimal: it
resolves base paths, loads immutable configuration, and provides a
lightweight service container. HTTP, CLI, background workers, and the
test suite all share the same bootstrap (`bootstrap/app.php`).

This is a conscious trade-off against using an established framework:
Katakata's calm-software philosophy — "every feature must justify its
existence" — applies to its own foundations as much as to user-facing
features. A framework brings capabilities Katakata doesn't need
(ORMs, queues, broadcasting, session drivers) at the cost of
architectural weight and "magic" that works against "the architecture
should remain understandable."

## Consequences

- Deployment is simple: PHP plus a webserver (or `php -S` for
  development) is enough to run the site. No required database
  server, no required Node build step for the backend.
- The kernel has no required third-party runtime dependencies;
  Composer is used only for optional developer tooling (PHPUnit), so
  `php public/index.php` and `php bin/katakata` work even without
  `composer install`.
- The team takes on responsibility for functionality a framework would
  otherwise provide (routing, DI, config) — but each piece stays small
  enough to read start-to-finish, which is the point.
- As real needs emerge (e.g. more sophisticated routing, middleware),
  they should be added deliberately and documented via new ADRs or
  subsystem docs — not reached for pre-emptively.
- This decision can be revisited if Katakata's operational
  requirements grow beyond what a lightweight kernel comfortably
  supports; nothing here is meant to be permanent dogma.

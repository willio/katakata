# ADR 0007: Filesystem Editorial Transactions

## Status

Accepted

## Context

Phase 3 adds editing, revisions, scheduling, and publishing while Markdown remains canonical and Composer remains optional. The project has no authentication subsystem, so an HTTP write surface would expose canonical content without an identity boundary.

## Decision

Editorial writes are exposed through the local CLI and implemented by framework-independent services under `app/Editorial/`.

- Drafts remain Markdown files under `content/drafts/`.
- Every replacement or publication captures the previous draft in `storage/revisions/{slug}/`.
- Writes use a temporary file in the destination directory followed by an atomic rename.
- Scheduling is front matter (`status: scheduled`, `publish_at` in ISO 8601).
- Publishing writes the canonical post first and removes the draft only after the post succeeds.
- A browser editor is deferred until authentication and authorization have an accepted contract.

## Consequences

Markdown remains authoritative and revisions stay inspectable and portable. Publication is recoverable from its revision if draft removal succeeds but a later generated output fails. CLI-only editing is intentionally less convenient than the eventual browser experience, but does not create an unauthenticated mutation endpoint.

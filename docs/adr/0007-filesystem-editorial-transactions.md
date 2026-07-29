# ADR 0007: Filesystem Editorial Transactions

## Status

Accepted

## Context

Phase 3 adds editing, revisions, scheduling, and publishing while Markdown remains canonical and Composer remains optional. The browser editor requires an identity boundary: email/password authentication with passkey support.

## Decision

Editorial writes are exposed through the local CLI and implemented by framework-independent services under `app/Editorial/`.

- Drafts remain Markdown files under `content/drafts/`.
- Every replacement or publication captures the previous draft in `content/revisions/{slug}/`.
- Writes use a temporary file in the destination directory followed by an atomic rename.
- Scheduling is front matter (`status: scheduled`, `publish_at` in ISO 8601).
- Publishing writes the canonical post first and removes the draft only after the post succeeds.
- The browser editor must use the shared authenticated session; passkeys are an additional sign-in method, not a separate identity.

## Consequences

Markdown remains authoritative and revisions remain canonical, inspectable, and portable. Publication is recoverable from its revision if draft removal succeeds but a later generated output fails. CLI editing remains available for maintenance and automation. Browser writes are not exposed until the authentication boundary is complete.

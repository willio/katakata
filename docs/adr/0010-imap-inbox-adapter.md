# ADR 0010: IMAP as the First Inbound Mail Adapter

## Status

Accepted for Katakata 1.0RC design; implementation follows only after the
mail workspace and correspondence privacy contracts are planned.

## Context

Katakata needs a useful editorial inbox for correspondence with readers. It
must not become a general-purpose Gmail replacement or introduce a second
canonical correspondence store through provider webhooks. Existing mail domain
objects already depend on a provider-neutral `MailboxProvider` boundary.

## Decision

Use IMAP as the first inbound adapter behind `MailboxProvider`.

- IMAP credentials remain deployment-only configuration: environment variables
  or the host's secret manager. They are never persisted by Katakata.
- A scheduled worker synchronizes IMAP into private operational state.
- Dashboard and Mail requests read that synchronized state; they never make
  network IMAP calls during page rendering.
- The workspace reports readiness, last successful sync, and degraded/stale
  state without exposing credentials.
- The first scope is inbox listing, message reading, read/unread, archive,
  reply drafts, and conservative attachment handling. Full MIME rendering,
  advanced threading, spam classification, and mailbox administration are out
  of scope until separately designed.

## Failure model

An unconfigured adapter shows setup guidance. A configured but unreachable
adapter preserves the last synchronized inbox, marks it stale, and records a
safe operational error without blocking publishing, dashboard rendering, or
campaign work. A failed sync never deletes local synchronized messages.

## Privacy and retention

Reader addresses, subjects, bodies, and attachments are sensitive operational
data. They are excluded from Git, public paths, analytics, and diagnostic
logs. Before implementation, the mail subsystem must define private storage
modes, a retention policy, deletion/export handling, and attachment limits.
Encryption at rest is a self-host deployment responsibility unless a managed
service explicitly provides and documents it.

## Consequences

IMAP support introduces protocol and synchronization complexity, but keeps
inbound correspondence portable and separates real-time page performance from
network reliability. Webhook-based inbound mail is not a competing canonical
inbox path.

The Settings surface may guide connection setup and report non-secret
readiness (for example, configured, stale, or unreachable). It may not accept,
store, or reveal IMAP usernames, passwords, OAuth refresh tokens, or other
mail credentials. This keeps local self-hosting testable through `.env` or the
host secret manager without creating an application-managed secret store.

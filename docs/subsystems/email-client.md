# Email client subsystem

Katakata treats reader correspondence as private operational data and exposes it through a provider-neutral mailbox boundary.

## Current scope

The current implementation provides:

- `Mailbox` as the application-facing read boundary.
- `MailboxProvider` for inbound adapters.
- `UnavailableMailboxProvider` as the safe default.
- Message, attachment, and draft value objects.
- A private filesystem draft store for future reply composition.

No request-time network access occurs. The default provider returns an empty inbox, zero unread messages, and a non-secret `needs_setup` readiness state.

## IMAP boundary

ADR 0010 selects IMAP as the first inbound adapter, but protocol implementation is deliberately deferred. A future scheduled sync process will fetch remote mailbox state into private operational storage. Dashboard and Mail requests must read only that cached state.

The web request path must never:

- connect to IMAP;
- parse remote MIME payloads;
- expose credentials;
- block publishing or campaign work when inbox sync is unavailable.

## Privacy and storage

Reader messages, attachments, and correspondence drafts must remain outside Git, public roots, analytics, and diagnostic logs. Runtime files use private directory and file modes where supported. Encryption at rest remains a deployment responsibility for self-hosted installations.

## Outbound boundary

`OutboundMailProvider` defines future reply delivery. It is distinct from newsletter campaign composition and does not create a second campaign transport contract. No concrete correspondence sender is registered until its delivery and retention behavior are specified.

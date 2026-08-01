# Email client subsystem

Katakata treats reader correspondence as private operational data and exposes
it through one provider-neutral Mail workspace.

## Canonical surface

`/mail` is the only canonical Mail workspace. It contains two distinct work
areas backed by one attention model:

- **Inbox** for reader correspondence readiness and cached mailbox state;
- **Campaigns** for newsletter review, confirmation, delivery, history, and
  retryable work.

Legacy `/dashboard/mail` redirects to `/mail`. It does not register a second
inbox surface.

## Current implementation

The current implementation provides:

- `Mailbox` as the application-facing read boundary;
- `MailboxProvider` for inbound adapters;
- `UnavailableMailboxProvider` as the safe default;
- message, attachment, and draft value objects;
- a private filesystem draft store;
- `MailAttention` for combined Inbox and campaign attention;
- one Mail landing decision based on the newest actionable state.

The default provider returns an empty inbox, zero unread messages, and a
non-secret `needs_setup` readiness state. Campaign work remains usable when
Inbox is unavailable.

## Attention contract

`MailAttention::summary()` returns:

```text
{
    reader: int,
    campaigns: int,
    total: int,
    detail: string
}
```

The detail string uses precise split copy such as:

```text
2 reader messages · 1 campaign needs attention
```

`MailAttention::landing()` returns `inbox` or `campaigns`. It compares the
latest unread cached message with the latest campaign awaiting review or retry.
If neither requires attention, it opens Inbox. If mailbox readiness is not
`ready`, it opens Campaigns and shows Inbox setup guidance.

Neither method performs network access.

## IMAP boundary

ADR 0010 selects IMAP as the first inbound adapter, but protocol implementation
is deliberately deferred. A scheduled sync process will fetch remote mailbox
state into private operational storage. Dashboard and Mail requests read only
that cached state.

The web request path must never:

- connect to IMAP;
- parse remote MIME payloads;
- expose credentials;
- block dashboard, publishing, or campaign work when inbox sync is unavailable.

The Mail workspace may show the last successful sync and a degraded or stale
state once cached sync exists. It must not display credential values.

## Privacy and storage

Reader messages, attachments, and correspondence drafts must remain outside:

- Git;
- public roots;
- reader analytics;
- diagnostic logs.

Runtime files use private directory and file modes where supported. Encryption
at rest remains a deployment responsibility for self-hosted installations.
Retention, deletion/export behavior, and attachment policy must be documented
before real inbox synchronization is enabled.

## Outbound boundary

`OutboundMailProvider` defines future correspondence delivery. It is separate
from newsletter campaign delivery and does not create a second campaign
transport contract. No concrete correspondence sender is registered until its
delivery, retention, and failure behavior are specified.

## Deferred scope

The following remain outside the current workspace:

- network IMAP implementation;
- scheduled synchronization worker;
- MIME parsing;
- attachment download and retention policy;
- spam handling;
- correspondence sending;
- credential editing in Settings.

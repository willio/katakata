# Email client subsystem

Katakata treats reader correspondence as private operational data and exposes it through one provider-neutral Mail workspace.

## Canonical surface

`/mail` is the canonical Mail workspace. It contains two distinct work areas backed by one attention model:

- **Inbox** for cached reader correspondence, drafts, sent mail, and archive;
- **Campaigns** for newsletter review, confirmation, delivery, history, and retryable work.

Legacy `/dashboard/mail` redirects to `/mail`. It does not register a second inbox surface.

## Current implementation

The implementation provides:

- `Mailbox` as the application-facing correspondence boundary;
- `MailboxProvider` for cache-backed inbound adapters;
- `CachedMailboxProvider` for request-time reads from private operational storage;
- `UnavailableMailboxProvider` as a controlled setup state;
- `SocketImapMailboxSource` as the extension-free scheduled source;
- `ImapSynchronizer` for bounded private cache merge and retention;
- private filesystem draft and sent-message stores;
- local read, archive, and cached-copy deletion state;
- `MailAttention` for combined Inbox and campaign attention;
- owner/admin authorization for Mail routes.

Campaign work remains usable when Inbox synchronization is unavailable.

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

`MailAttention::landing()` returns `inbox` or `campaigns`. It compares the latest unread cached message with the latest campaign awaiting review or retry. If neither requires attention, it opens Inbox. If mailbox readiness is not `ready`, it opens Campaigns and shows Inbox setup guidance.

Neither method performs network access.

## IMAP boundary

ADR 0010 selects IMAP as the first inbound adapter. The implementation uses direct IMAP-over-TLS through PHP streams and OpenSSL; it does not require `ext-imap` or a Composer IMAP library.

The scheduled source supports only the bounded command set required for synchronization:

- `LOGIN`;
- `SELECT`;
- UID search;
- bounded UID fetch.

Only `IMAP_ENCRYPTION=ssl` is accepted. Plaintext and STARTTLS modes are rejected. Credentials remain in the environment or host secret manager and are never rendered, cached, or logged.

The web request path must never:

- connect to IMAP;
- parse remote MIME payloads;
- expose credentials;
- mutate the remote mailbox;
- block dashboard, publishing, or campaign work when synchronization is unavailable.

## Cache and MIME policy

The scheduled synchronizer stores only message identity, selected headers, extracted plain text, and receipt time. It deliberately excludes:

- attachment metadata and payloads;
- remote HTML rendering;
- remote mailbox flags or mutations;
- correspondence export.

Attachments remain in the original mailbox application. Cached correspondence is retained for 30 days from `received_at`. A smaller synchronization window merges with existing unexpired cache entries rather than replacing the visible inbox.

Source failure preserves the prior cached message set and last successful synchronization time.

## Local correspondence state

Read and archive state are local operational preferences. They do not issue IMAP commands.

`Delete cached copy` removes only the private cached record and associated local state. A 30-day tombstone prevents immediate restoration by a later synchronization. The original message remains unchanged in the remote mailbox.

## Privacy and storage

Reader messages, correspondence drafts, sent records, synchronization state, and deletion tombstones remain outside:

- Git;
- public roots;
- reader analytics;
- diagnostic payload logs.

Runtime files use private directory and `0600` file modes where supported. Encryption at rest remains a deployment responsibility for self-hosted installations.

See [`docs/operations/imap-mailbox-sync.md`](../operations/imap-mailbox-sync.md) for deployment and controlled-sync guidance.

## Outbound boundary

`OutboundMailProvider` defines correspondence delivery. It remains separate from newsletter campaign delivery and does not create a second campaign transport contract. Successful correspondence sends are recorded privately; failed sends preserve the draft.

## Deferred scope

The following remain outside the current implementation:

- spam classification and quarantine;
- remote mailbox mutations;
- attachment caching or download;
- correspondence export;
- credential editing in Settings;
- additional IMAP authentication mechanisms;
- multiple mailbox accounts.

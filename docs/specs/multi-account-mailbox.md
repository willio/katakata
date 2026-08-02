# Multi-account Mailbox and Account Profile Import

## Status

Approved implementation specification.

## Objective

Expand Katakata Mail from one cached IMAP mailbox into a deliberately small multi-account editorial inbox. Up to three enabled mailbox accounts synchronize independently into private operational caches and are presented as one chronological Inbox while retaining the source mailbox identity on every message.

The design preserves the existing boundaries: HTTP requests never open IMAP connections, remote mailboxes are never mutated, only extracted plain text is cached, attachments are not cached, credentials remain deployment secrets, and cached correspondence is retained for 30 days.

## Account model

Each mailbox has an immutable stable ID and a human label. Non-secret account configuration is stored privately; credentials are resolved from environment/secret-manager variable names.

Required fields:

- `id`: `[a-z0-9][a-z0-9_-]{1,31}`; immutable after creation.
- `label`: human-readable source label.
- `host`.
- `port`; normally 993.
- `encryption`: `ssl` only.
- `mailbox`: `INBOX` in the first release.
- `username_secret`: deployment variable name containing the username.
- `password_secret`: deployment variable name containing the password.
- `enabled`.

Maximum configured accounts in the first release: three.

`storage/mail/accounts.json` is the canonical private registry and is written mode `0600` where supported. Secret values are never stored in this file.

## Storage

```text
storage/mail/
├── accounts.json
├── cache/
│   ├── letters/
│   │   ├── index.json
│   │   ├── state.json
│   │   └── messages/*.json
│   ├── editorial/
│   └── admin/
├── drafts/
└── sent/
```

Every account has an isolated cache, status, local read/archive state, deletion tombstones, and last successful synchronization time. Account isolation prevents UID collisions and allows one mailbox to fail without corrupting another.

Cached message identity is globally qualified by account, e.g. `letters:uid-1042`. The record includes `source_account_id`, `source_label`, and `source_message_id`. The source account ID is authoritative; the label is a presentation snapshot.

## Synchronization

`MailboxSyncCoordinator` synchronizes all enabled accounts or one selected account. Each account gets its own `ImapSettings`, `SocketImapMailboxSource`, `ImapSynchronizer`, and cache path.

```bash
php private/jobs/sync-mail.php
php private/jobs/sync-mail.php --account=letters
php private/jobs/sync-mail.php --account=letters --limit=50
```

A failure in one account must not prevent healthy accounts from synchronizing. Output reports only account ID/label, state, counts, and non-secret errors.

Existing cache rules remain mandatory per account:

- direct TLS only;
- no `ext-imap` requirement;
- selected bounded `text/plain` fetch only;
- attachment parts excluded, including `text/plain` attachments;
- no HTML persistence;
- no attachment metadata or payload persistence;
- 30-day retention;
- local-only read/archive/delete;
- deletion tombstones retained for the same window;
- remote mailbox never mutated.

## Aggregated Inbox

`/mail` defaults to all enabled accounts. Cached summaries are merged and sorted globally by `received_at` descending.

Inbox navigation exposes:

```text
Inbox
  All
  Letters
  Editorial
  Admin
```

Each account may show unread count or readiness. A failed account remains visible as `Needs attention` while healthy cached mail remains usable.

Filtering uses `/mail?account={id}`; `account=all` is equivalent to the default.

Message routes explicitly carry account identity:

```text
/mail/messages/{accountId}/{messageId}
```

Read, archive, and local delete actions operate only on the source account cache. Message list and detail views always show the source mailbox label.

Overall readiness:

- `Ready`: all enabled accounts are ready.
- `Partially available`: at least one account is usable and at least one needs attention/setup.
- `Needs setup`: accounts exist but none are usable.
- `Disabled`: no account is enabled.

## Settings account CRUD

Owner/admin only; all mutations require CSRF.

Routes:

```text
POST /dashboard/settings/mailboxes
POST /dashboard/settings/mailboxes/{id}
POST /dashboard/settings/mailboxes/{id}/enable
POST /dashboard/settings/mailboxes/{id}/disable
POST /dashboard/settings/mailboxes/{id}/delete
POST /dashboard/settings/mailboxes/{id}/sync
```

Settings edits only non-secret configuration and secret variable names. It never accepts, stores, or renders credential values.

Account deletion requires explicit confirmation and offers two local choices:

1. remove account configuration while preserving private cache;
2. remove configuration and private cached copies.

Neither choice mutates the remote mailbox.

## Account profile import

Profile import is a convenience adapter into the neutral mailbox account model. Imported profile formats never become canonical storage.

First release accepts:

- Apple `.mobileconfig` XML configuration profiles;
- XML plist containing `com.apple.mail.managed` payloads.

Later adapters may support Thunderbird autoconfig, Microsoft Autodiscover, or RFC 6186 discovery, but are outside this slice.

Import workflow:

```text
Settings → Mailbox accounts → Import account profile
→ upload profile → parse locally → review candidates
→ choose candidate → map secret variable names → save account
```

Nothing is persisted before explicit review/confirmation.

The importer extracts supported IMAP payload fields such as account description, email address, incoming host/port/SSL/authentication/username, mailbox, and outgoing SMTP metadata where present. POP accounts are rejected. Multiple supported Mail payloads produce independently selectable candidates.

SMTP values may be shown in review but are not persisted into the inbound account registry until outbound per-account identity is separately designed.

### Credential boundary

Embedded passwords are never persisted, rendered back, or logged. Their presence is reported only as `Embedded credential detected`.

Certificate/private-key identity payloads are not automatically installed or persisted. Profiles requiring identity material are rejected for automatic setup and require manual deployment configuration.

Imported username values may be shown during review, but the saved account uses configured secret variable names for deployment credential resolution.

### Import security

- owner/admin only;
- CSRF on upload confirmation;
- maximum upload 256 KiB;
- XML/plist only;
- no external entities;
- no network access during parsing;
- no recursive profile retrieval;
- no certificate installation;
- no MDM execution;
- no arbitrary object deserialization;
- unrelated Wi-Fi/VPN/restriction/MDM payloads ignored;
- parsed candidates are short-lived, private, mode `0600`, credential-free, and single-use.

Temporary import records may live under `storage/mail/imports/{token}.json` for at most 15 minutes.

Routes:

```text
GET  /dashboard/settings/mailboxes/import
POST /dashboard/settings/mailboxes/import
POST /dashboard/settings/mailboxes/import/confirm
```

## Legacy migration

If no account registry exists but the legacy single-account IMAP deployment variables are configured, expose them as a generated `default` account and preserve the existing `storage/mail/cache` data during migration. Migration must not lose cached mail or expose credentials.

## First-release exclusions

- more than three accounts;
- remote IMAP folder management;
- remote flags/moves/deletes;
- POP;
- cached attachments;
- HTML message persistence/rendering;
- per-account outbound SMTP identity selection;
- certificate/private-key installation;
- network-based autodiscovery during profile import.

## Acceptance tests

Required coverage:

1. identical UIDs from two accounts remain distinct;
2. three account caches merge into one chronological Inbox;
3. one failed account does not block healthy accounts;
4. source label appears in list and detail views;
5. read/archive/delete affects only the source cache;
6. deleting one account cannot remove another account cache;
7. disabled accounts are neither synchronized nor shown in the active Inbox;
8. editor cannot manage accounts, import profiles, or trigger sync;
9. Settings never renders credential values;
10. three-account limit and immutable IDs are enforced;
11. legacy single-account cache migrates without message loss;
12. `.mobileconfig` imports one or more supported IMAP payloads;
13. POP and identity-material profiles are rejected for automatic setup;
14. embedded passwords never reach persisted import/account data;
15. unrelated configuration-profile payloads are ignored;
16. imported candidates require explicit confirmation before account creation.

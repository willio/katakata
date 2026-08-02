# IMAP Mailbox Synchronization

Katakata never opens an IMAP connection during an HTTP request. Inbox pages read only from the private operational cache under `storage/mail/cache`.

## Deployment variables

Configure these values in the deployment environment or host secret manager:

```text
IMAP_HOST
IMAP_PORT=993
IMAP_ENCRYPTION=ssl
IMAP_USERNAME
IMAP_PASSWORD
IMAP_MAILBOX=INBOX
```

Only direct TLS is supported. `IMAP_ENCRYPTION` must be `ssl`; plaintext and STARTTLS modes are rejected. The implementation uses PHP streams and OpenSSL and does not require `ext-imap` or a Composer IMAP dependency.

Credentials must never be stored in dashboard settings, committed files, logs, fixtures, cached status, or rendered HTML.

`IMAP_HOST` must resolve directly to the mail server. Do not put it behind an
HTTP/CDN proxy such as Cloudflare's proxied DNS mode: direct IMAPS on port 993
requires a DNS-only mail hostname and a valid TLS certificate for that host.

## Manual verification

Run the synchronizer from the application root:

```bash
php private/jobs/sync-mail.php
```

Optionally limit the remote fetch window:

```bash
php private/jobs/sync-mail.php 5
```

A successful run merges fetched messages into the existing cache, removes correspondence older than 30 days, and records `last_synced_at`. A smaller later fetch window does not hide unexpired cached mail.

A failed run preserves the existing message set and the last successful synchronization timestamp while recording a non-secret error state.

## Scheduler

Run the job outside request handling. A five-minute cron example is:

```cron
*/5 * * * * cd /path/to/katakata && /usr/bin/php private/jobs/sync-mail.php 100 >> storage/logs/mail-sync.log 2>&1
```

Use the deployment's actual PHP binary and project path. Ensure the scheduler user can read the secret-manager environment and write `storage/mail/cache`.

## Requested refreshes

The Inbox **Get new mail** action does not connect to IMAP. It writes one
private, coalesced refresh request for the next scheduled worker run, then
reports that the refresh was requested. The next run consumes that marker as it
begins its normal sync. Repeated clicks do not create a queue or extra IMAP
connections.

## Readiness states

- `Ready`: TLS transport is available and the private cache has a successful synchronization state.
- `Needs setup`: required deployment variables are missing, encryption is not `ssl`, OpenSSL is unavailable, or the cache has not been populated.
- `Needs attention`: the latest scheduled synchronization failed; existing cached mail remains readable.

Settings may expose host, port, encryption, mailbox, transport availability, missing variable names, and synchronization state. It must never expose the IMAP username or password.

## Cache policy

The cache is intentionally bounded:

- headers and extracted plain text only;
- no HTML rendering from the remote message;
- no attachment metadata or payload storage;
- 30-day retention from `received_at`;
- local read and archive state only;
- no export surface;
- no remote mailbox mutations.

Attachments remain available only through the original mailbox application.

## Local deletion

`Delete cached copy` removes the local message record and associated read/archive state. It does not delete, move, mark, or otherwise mutate the remote IMAP message.

A private 30-day tombstone prevents the next scheduled synchronization from immediately restoring a locally deleted message. Tombstones expire with the same retention window.

## Storage and permissions

Operational files include:

```text
storage/mail/cache/index.json
storage/mail/cache/state.json
storage/mail/cache/messages/*.json
storage/mail/refresh-request.json
```

Files are written with mode `0600` where supported and must remain outside Git and the public document root. Legacy attachment cache directories are removed during synchronization.

## Controlled deployment checklist

Before enabling scheduled synchronization against a real mailbox:

```text
[ ] No IMAP credential appears in Git diff, logs, Settings HTML, or test output.
[ ] A five-message sync creates only index, state, tombstone, and message JSON files.
[ ] No attachment payload directory exists under storage/mail/cache.
[ ] No cached message is older than 30 days.
[ ] Local deletion leaves the remote mailbox unchanged.
[ ] A failed synchronization preserves existing cached messages.
```

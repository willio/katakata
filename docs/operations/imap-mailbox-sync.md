# IMAP Mailbox Synchronization

Katakata never opens an IMAP connection during an HTTP request. Inbox pages read only from the private cache under `storage/mail/cache`.

## Deployment variables

Configure these values in the deployment environment or secret manager:

```text
IMAP_HOST
IMAP_PORT
IMAP_ENCRYPTION
IMAP_USERNAME
IMAP_PASSWORD
IMAP_MAILBOX
```

`IMAP_ENCRYPTION` accepts `ssl`, `tls`, or `none`. Credentials must not be stored in dashboard settings, committed files, logs, or fixtures.

## Manual verification

Run the synchronizer from the application root:

```bash
php private/jobs/sync-mail.php
```

Optionally limit the number of remote messages fetched:

```bash
php private/jobs/sync-mail.php 50
```

A successful run updates the private cache and records `last_synced_at`. A failed run preserves previously cached messages and records the latest non-secret error state.

## Scheduler

Run the job outside request handling. A five-minute cron example is:

```cron
*/5 * * * * cd /path/to/katakata && /usr/bin/php private/jobs/sync-mail.php 100 >> storage/logs/mail-sync.log 2>&1
```

Use the deployment's actual PHP binary and application path. Ensure the scheduler user can read the secret-manager environment and write `storage/mail/cache`.

## Readiness states

- `Ready`: the private cache is readable; Settings shows the last successful synchronization time.
- `Needs setup`: required deployment variables are missing or the cache has not been populated.
- `Needs attention`: the latest scheduled synchronization failed; existing cached mail remains available.

The Settings page exposes host, port, encryption, mailbox, missing variable names, and synchronization state. It must never expose the IMAP username or password.

## Storage and permissions

Cached indexes, messages, attachment payloads, and local read/archive state are operational data. Files are written with mode `0600` and must remain outside Git and the public document root.

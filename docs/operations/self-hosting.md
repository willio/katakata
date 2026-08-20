# Self-Hosting

Katakata is a framework-light PHP application. Markdown under `content/` is
canonical, `storage/` holds reproducible operational state, and only `public/`
is web-accessible. This guide covers a generic installation; subsystem details
live in the linked documents.

## Requirements

- PHP 8.2 or later (the development target is 8.5) with OpenSSL.
- sodium, which encrypts the application-managed secrets store, and
  PDO SQLite, which powers analytics; both are bundled with standard PHP builds.
- Composer, only to run the test suite. The application itself never requires
  Composer's autoloader at runtime.
- A web server with `public/` as the document root. Local development uses
  nginx (`config/nginx/katakata.conf`, see the README); the reference
  production deployment runs on Apache.
- Optional: PHP's DOM and ZIP extensions plus LibreOffice for legacy document
  import. See [`docs/subsystems/import.md`](../subsystems/import.md).

## Install

```bash
git clone git@github.com:willio/katakata.git
cd katakata
composer install        # optional outside development; needed for tests
cp .env.example .env
```

Set at minimum in `.env`:

- `APP_NAME` — the publication name.
- `APP_URL` — the canonical origin, including scheme.
- `APP_KEY` — a long random value, for example `openssl rand -hex 32`. It is
  the fallback newsletter/analytics signing secret and encrypts the
  application-managed secrets store.

Make `storage/` writable by the PHP user. Sensitive operational files are
owner-only by design: settings and secrets are written `0600` and their
directories `0700`. Do not broaden these modes.

## First run

Create the owner account, then serve:

```bash
php bin/katakata auth:owner <email> <password>
php bin/katakata serve
```

(The CLI entry point is still named `bin/katakata`; the platform rename is
pending.)

Visit the homepage and `/dashboard` to sign in. Run
`php bin/katakata content:validate` to confirm the shipped example content
parses. Further accounts are invite-only via
`php bin/katakata auth:invite <email> [admin|editor]`.

## Content model

Markdown is canonical and the repository is the authoritative copy. `content/`
holds `posts/`, `drafts/`, `authors/`, `assets/`, `revisions/`, and `legacy/`;
everything under `storage/` is generated or operational and rebuildable. The
folder ships with example content. See
[`docs/subsystems/content-engine.md`](../subsystems/content-engine.md).

## Optional subsystems

Each subsystem is disabled or degrades safely until configured.

- Newsletter delivery: set `NEWSLETTER_SECRET`, `MAIL_TRANSPORT=resend`,
  `RESEND_API_KEY`, and `RESEND_WEBHOOK_SECRET`. See
  [Resend production setup](../subsystems/distribution.md#resend-production-setup-for-katakatacom).
- Analytics: set `ANALYTICS_SECRET` (falls back to `APP_KEY`), then verify with
  `php bin/katakata analytics:check`. Visits are recorded without raw IP
  addresses. See [`docs/subsystems/analytics.md`](../subsystems/analytics.md).
- Threads discussion and distribution: disabled unless `THREADS_ENABLED=true`.
  Credential precedence and settings behavior are documented in
  [`docs/subsystems/settings.md`](../subsystems/settings.md).
- Mailbox synchronization: deployment-only IMAP over direct TLS, run from the
  scheduler, never during HTTP requests. See
  [`docs/operations/imap-mailbox-sync.md`](./imap-mailbox-sync.md).

## Backups

The backup manager and its integrity and permission contract are documented in
[`docs/subsystems/backups.md`](../subsystems/backups.md). Archives contain
sensitive operational data and must stay `0600` in a `0700` directory, outside
every public root and outside Git. A restore runbook does not exist yet;
restoring is a known documentation gap tracked on the roadmap.

## Upgrading

```bash
git pull
composer install
composer test
```

No migration step is required: canonical content is Markdown in Git, the
analytics SQLite schema boots itself, and other operational state under
`storage/` is rebuildable from canonical content.

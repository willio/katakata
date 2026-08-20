# Settings subsystem

Katakata separates application-wide settings from post metadata and deployment configuration.

## Canonical surface

`/dashboard/settings` is the only canonical application-wide settings surface. The editor may link to it, but must not duplicate global controls.

## Ownership

### Global application and publication settings

These values belong to `/dashboard/settings` and may be stored in `storage/settings/application.json` when they are safe runtime preferences:

- Publication name, description, default author, and presentation defaults.
- Newsletter defaults such as the default sender label and per-post newsletter default.
- Discussion provider selection and whether discussion is enabled by default.
- Analytics display preferences.
- Appearance defaults.
- Links to account, security, diagnostics, and operational status.

### Per-post editor settings

These remain in the post or draft front matter and apply only to the current post:

- Slug.
- Author override.
- Publication date and schedule.
- Excerpt.
- SEO title, description, canonical override, and featured/social image.
- `publish_as_newsletter` for the current post.
- Discussion enablement for the current post.
- Draft, scheduled, or published state.

Post metadata must never be promoted automatically into global defaults.

### Deployment-only configuration

These remain in `.env` or machine configuration and are never written by the dashboard:

- `APP_URL` and deployment origin.
- API keys, access tokens, webhook secrets, and provider credentials.
- Mail transport credentials and infrastructure endpoints.
- Filesystem paths, TLS material, and server configuration.
- Authentication secrets and encryption material.

The dashboard may report whether required deployment values are present, but it must not render their values.

Deployment readiness is shown as a calm non-secret setup state in the settings
UI. It is distinct from editable global preferences and must never turn the
settings page into an infrastructure console.

For the Mail workspace, this readiness state covers an IMAP inbox adapter as
defined by [ADR 0010](../adr/0010-imap-inbox-adapter.md). Settings may provide
a concise self-hosting setup checklist and identify a missing, stale, or
unreachable connection, but it never renders or accepts credentials. IMAP
usernames, passwords, and OAuth tokens are supplied through `.env` or the
host's secret manager.

### Account and security management

Passwords, passkeys, sessions, invitations, and account identity remain owned by the authentication subsystem. The settings page links to those controls rather than storing security data in the application settings file.

## Appearance settings

The `appearance` section holds presentation preferences, persisted in `storage/settings/application.json`:

- `button_style`: owner action-button shape. `regular` (default) renders the standard 6px radius; `pill` opts into the pill variant defined by [the fields & buttons styleguide](../fields-buttons-styleguide.md). When `pill` is active, owner pages render a `buttons-pill` class on `<body>` and the overrides in `public/assets/css/boundary.css` apply. Applies to owner pages only — the views that load `boundary.css`; public views (home, article, archive, author, newsletter) are unaffected.

## Persistence contract

Runtime settings are stored atomically in `storage/settings/application.json`.

- Reading a missing settings file returns empty settings and creates no files or directories.
- Only known sections and keys are accepted.
- A section update validates the complete section before any write.
- A failed update leaves all persisted sections unchanged.
- Empty secret fields must preserve an existing secret. Explicit removal requires a distinct action.
- The settings file must not contain API keys, passwords, passkey material, TLS keys, or other deployment secrets.

## Optional integrations

Optional services must remain inert when disabled.

- Threads may be disabled with no user ID or access token.
- Enabling Threads without required deployment credentials produces validation errors, not a runtime fatal error.
- Filesystem mail transport does not require Resend credentials.
- Opening `/dashboard/settings` or the editor must not instantiate external provider clients.

The discussion manager registers the Threads provider only when Threads is
enabled and both `THREADS_USER_ID` and `THREADS_ACCESS_TOKEN` are present.
Otherwise a request for Threads resolves the null provider. The settings
boundary performs the same credential-presence check before accepting Threads
as the selected provider; credential values remain opaque deployment state and
are never copied into application settings. Dashboard discussion summaries read
the selected provider through this boundary rather than consulting deployment
configuration directly.

## Editor boundary

The editor settings panel is limited to post-scoped controls. The current
surface retains title and slug derivation plus the current post's
`publish_as_newsletter` and `discussion_enabled` flags. Existing front matter
outside those submitted controls survives autosave unchanged. Account and
passkey controls do not live in the post settings panel, which includes a
restrained link to `/dashboard/settings` for application-wide configuration.
Opening the editor settings panel does not resolve mail transports, analytics
stores, discussion API clients, or other unrelated global services.

No settings migration is required for this reconciliation: global runtime
preferences had no earlier canonical store. Post metadata remains in Markdown
and is deliberately excluded from migration.

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

### Account and security management

Passwords, passkeys, sessions, invitations, and account identity remain owned by the authentication subsystem. The settings page links to those controls rather than storing security data in the application settings file.

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

## Editor boundary

The editor settings panel is limited to post-scoped controls. It includes a restrained link to `/dashboard/settings` for application-wide configuration. Opening the editor settings panel must not resolve mail transports, analytics stores, discussion API clients, or other unrelated global services.

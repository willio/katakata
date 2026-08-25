# ADR 0011: Application-managed Secrets

## Status

Accepted 2026-08-20. Tier 0 (secret-by-reference Threads keys) shipped first
and remains the default; this acceptance covers the Tier 1 scope: selecting
`provider=threads` in Settings activates Threads without requiring
`THREADS_ENABLED`, and the Threads access token value may optionally be
managed from Settings through the encrypted store described below.

## Context

Katakata's settings subsystem (`docs/subsystems/settings.md`) and ADR 0010
both keep credentials deployment-only: API keys, access tokens, and provider
credentials live in `.env` or the host's secret manager, and the dashboard may
report their presence but never accept, store, or render their values. That
stance is correct for deployments where the operator can edit `.env` directly.

It is not correct for all of them. Self-hosted deployments on managed hosting
(for example the Apache/Dewaweb target in ADR 0002) can make `.env` editing
impractical: no shell access, a file manager round-trip per change, and no
secret manager at all. For those operators, deployment-only credentials mean
integrations such as Threads distribution cannot be configured without
off-board assistance.

The Tier 0 settings work shipped a narrower answer: discussion settings store
`threads_user_id` (a non-secret account identifier) and
`threads_token_secret` (the *name* of an environment variable holding the
Threads access token), following the secret-by-reference pattern of the
multi-account mailbox specification (`docs/specs/multi-account-mailbox.md`).
Secret values stay in `.env` or the host secret manager; the application never
accepts, stores, renders, or logs them. Tier 0 resolves the settings/wiring
questions without deciding whether the application should ever hold a secret
value itself.

This ADR decides the missing piece: an application-managed secret store for
operators who cannot maintain `.env`. It changes the project's secret-handling
posture deliberately and narrowly; the initial scope is the Threads access
token (`threads.access_token`) only.

## Decision

Katakata gains a small application-managed secret store at
`storage/settings/secrets.json`, alongside but separate from
`storage/settings/application.json`.

- **Encryption at rest.** Values are encrypted with libsodium
  (`sodium_crypto_secretbox`) before persistence, keyed from `APP_KEY`. The
  file at rest contains only ciphertext; `APP_KEY` remains deployment-only
  configuration and is never written to any settings file.
- **Filesystem protection.** The `storage/settings/` directory is mode `0700`
  and `secrets.json` is mode `0600`, matching the authentication store
  convention from ADR 0008. The file lives outside every public document root
  and outside Git; `.gitignore` covers `storage/settings/`.
- **Masked rendering.** The settings UI never renders a stored secret value.
  Fields render a presence indicator only (configured / not configured).
- **Empty preserves.** Submitting an empty secret field preserves the existing
  secret, per the persistence contract in `docs/subsystems/settings.md`.
  Explicit removal is a distinct action.
- **Re-authentication.** Setting, revealing (if reveal is offered at all), or
  removing a stored secret requires fresh owner/admin re-authentication, not
  merely an active session.
- **Lazy resolution.** Secrets are decrypted only at the point of use, never
  at boot and never during settings page rendering, following the
  `MailboxCredentialResolver` precedent: the store answers "is it configured"
  cheaply and resolves the value only when a consumer needs it.
- **Precedence.** An application-managed secret, when set, overrides the
  equivalent deployment configuration value, matching the Tier 0
  settings-override rule for effective credentials.

This ADR **amends ADR 0010's "never persisted" stance** on credentials and
**amends the deployment-only list in `docs/subsystems/settings.md`** to carve
out this single encrypted store. Both documents were updated at acceptance.

## Consequences

- **Code execution becomes secret disclosure.** Today, an attacker who
  achieves PHP code execution still finds no persisted credentials beyond what
  `.env` already holds. With this store, any secret entered through the
  dashboard is recoverable by anyone who can read both `secrets.json` and
  derive `APP_KEY`. The store raises the stakes of every code-execution path
  in the application. This residual risk is accepted deliberately: the store
  is scoped narrowly (initially `threads.access_token` only), remains opt-in
  per deployment, and the Tier 0 by-reference pattern stays the default.
- **Backup sensitivity increases.** `secrets.json` becomes sensitive
  operational data under the backup privacy boundary in
  `docs/subsystems/backups.md`: archives containing it must remain in `0700`
  directories with `0600` archive and sidecar modes, outside public roots and
  Git. Operators backing up `storage/` now back up credential material.
- **Git hygiene remains load-bearing.** `storage/settings/` is covered by
  `.gitignore` and must stay that way; a single committed
  `secrets.json` would persist ciphertext whose key (`APP_KEY`) operators
  routinely commit by accident elsewhere.
- **The Tier 0 by-reference pattern remains the default.** Application-managed
  secrets are a fallback for deployments that cannot maintain `.env`, not a
  replacement for secret-by-reference. Deployments with a working `.env` or
  secret manager should continue using variable names; nothing in Tier 0
  changes.
- **Key rotation is an open operational question.** Rotating `APP_KEY`
  invalidates the store unless a re-encryption migration is provided. The
  first implementation may document rotation as "re-enter secrets after
  rotating `APP_KEY`" rather than building migration machinery.

## Alternatives considered

- **Browser writes to `.env`.** Rejected. Katakata's environment loading is
  a deliberately minimal parser; round-tripping arbitrary user values through
  it invites quoting, escaping, and comment-preservation bugs. Worse, real
  environment variables override `.env` values, so a dashboard write could be
  silently ineffective on hosts that set variables at the process level — a
  failure mode indistinguishable from success in the UI.
- **SQLite secret storage.** Rejected. SQLite is approved only as a narrow,
  observational exception for analytics data (ADR 0009; ADR 0014's operational
  data layer does not approve a general store). Credential material is neither
  observational nor rebuildable, and no ADR currently sanctions this use.
- **Plaintext JSON with `0600` permissions.** Rejected. Filesystem permissions
  are a single point of failure shared with every PHP process running as the
  same user, including any future compromised dependency. Encryption keyed
  from deployment-only `APP_KEY` keeps the file useless to an attacker who
  obtains only the file (for example through a misconfigured backup or an
  accidental commit).

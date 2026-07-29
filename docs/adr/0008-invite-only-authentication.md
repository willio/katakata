# ADR 0008: Invite-only Authentication

## Status

Accepted

## Context

The Phase 3 browser editor mutates canonical Markdown. It therefore needs an identity and authorization boundary before any editorial route is exposed. Katakata remains framework-light, database-free, and able to run without Composer.

## Decision

Accounts and invitations are stored in a permission-restricted JSON document under `storage/auth/`; this is runtime identity state, not canonical publishing content.

- The initial owner is created explicitly through `auth:owner`.
- There is no public signup.
- Owners and admins issue random, single-use invitations that expire after 48 hours.
- Invitation tokens are stored only as SHA-256 digests and are bound to an email and role.
- Passwords use PHP's `PASSWORD_DEFAULT`, a minimum length of 12 characters, and automatic rehashing after successful login.
- Authentication uses an HTTP-only, SameSite session cookie with session ID rotation at login.
- Every authenticated mutation requires a per-session CSRF token.
- Roles are `owner`, `admin`, and `editor`; only owners and admins can invite accounts.
- Passkeys are additional credentials on the same account and never create a parallel identity.

## Consequences

A lost authentication store cannot be reconstructed from Markdown and must be backed up as installation state. Authentication writes need the same atomicity discipline as editorial writes. WebAuthn verification must validate origin, RP ID, challenge, user presence, user verification, credential ownership, and signature before passkey login is complete. Nonzero authenticator signature counters must increase; authenticators that intentionally report zero remain valid under the WebAuthn counter model.

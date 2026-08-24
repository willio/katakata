# Editorial Subsystem

Phase 3 provides a filesystem-native editorial workflow.

## Boundaries

`DraftEditor` creates, updates, and schedules drafts. `RevisionStore` captures immutable pre-change Markdown under `content/revisions/`. `Scheduler` selects due drafts without side effects. `Publisher` moves a validated draft into the dated post convention through an atomic destination write.

The Repository remains the read boundary. Editorial services are the only write boundary.

Editor Close persistence and the recoverable canonical-content Trash lifecycle
are specified in [`docs/specs/editor-close-content-trash.md`](../specs/editor-close-content-trash.md).

## CLI workflow

```bash
php bin/katakata draft:create <slug> <title>
php bin/katakata draft:edit <slug>
php bin/katakata draft:schedule <slug> <ISO-8601>
php bin/katakata draft:publish <slug> [ISO-8601]
php bin/katakata publish:due
php bin/katakata revisions:list <slug>
```

`draft:edit` uses `$EDITOR`, captures the previous file as a revision, and installs the edited file atomically only when the editor exits successfully.

`draft:publish` and `publish:due` only create canonical posts. Newsletter delivery remains an explicit `newsletter:dispatch <post-slug>` operation, so distribution failures and queue work never affect publication.

## Safety

Slugs are restricted to lowercase URL-safe words. Existing publication targets are never overwritten. Invalid or missing drafts fail before mutation. Every destructive draft transition first captures a revision.


## Authentication and browser workflow

The browser editor is mounted at `/editor` and redirects anonymous requests to `/login`. It reuses `DraftEditor` and `Publisher`; HTTP routes do not write Markdown directly.

Bootstrap the first owner once:

```bash
php bin/katakata auth:owner owner@example.com 'a-password-of-at-least-12-characters'
```

Owners and admins can create 48-hour, single-use invitations in the editor. For installation and recovery, the equivalent CLI command is:

```bash
php bin/katakata auth:invite editor@example.com [admin|editor]
```

Account and invitation state lives at `storage/auth/accounts.json`, outside canonical content. The file is written atomically and restricted to the application user. Sessions rotate on login, use HTTP-only SameSite cookies, and require CSRF tokens for every mutation.

Authenticated users enroll passkeys from `/editor`; the login page supports passwordless passkey authentication after an email identifies the invited account. Passkey credentials live at `storage/auth/passkeys.json` and are written atomically with mode `0600`.

WebAuthn ceremonies use five-minute, single-use challenges stored in the session. Verification binds the challenge, account, exact `APP_URL` origin, RP ID, credential ownership, user presence, and user verification. Registration accepts only `none` attestation and P-256 or RSA public keys. Authentication verifies the assertion signature and advances any nonzero authenticator counter. Password login remains the recovery path.

Production passkeys require HTTPS. `http://localhost` remains valid for local WebAuthn development. The PHP OpenSSL extension is required for assertion signatures.


## Design and autosave

The browser editor implements `docs/design_specification.md` as a fullscreen 68ch monospace writing surface. Draft navigation, publishing, and current-post metadata remain in a hidden settings panel toggled by its quiet affordance or `Cmd/Ctrl+,`. The panel preserves per-post newsletter and discussion flags and links to `/dashboard/settings` instead of duplicating publication-wide or account controls.

The editor may offer **Create campaign** for the current post. This is a
handoff to the Mail workspace: it creates a separate campaign draft from the
post without sending, queuing, or mutating canonical Markdown. The detailed
compose/autosave contract is in
[`docs/superpowers/specs/2026-08-01-mail-workspace-compose-design.md`](../superpowers/specs/2026-08-01-mail-workspace-compose-design.md).

For existing drafts, `public/assets/js/editor.js` writes a debounced draft-specific local recovery buffer, synchronizes after seven seconds and on focus/visibility changes, and reports honest textual save state. `POST /editor/drafts/{slug}/autosave` reuses `DraftEditor`, returns the canonical content version, and confirms the client version. The buffer is cleared only when that exact version is acknowledged. A newer local buffer prompts before restoration. Multi-tab and multi-device editing remains documented last-write-wins behavior.

## Close and recoverable Trash

The editor's explicit **Close** action synchronously saves before returning to
`/posts`. A new document without a derivable first-line title becomes
`Unsaved draft`, using `unsaved-draft` and the normal collision suffixes. A
failed acknowledgement leaves the editor open and retains its recovery buffer.

Canonical drafts and posts move through `ContentTrash`; routes never unlink
Markdown directly. Trash stores exact bytes and a checksum-verified manifest
under `content/trash`, outside Repository discovery. Retention is indefinite,
restore refuses to replace an occupied canonical path, and permanent purge is
available only to a trusted host operator using the exact repeated identifier:

```bash
php bin/katakata trash:purge draft <trash-id> --confirm=<trash-id>
```

Editors may trash and restore drafts. Only owners and admins may trash or
restore published posts. Every browser mutation requires authentication, CSRF,
and POST. See `docs/specs/editor-close-content-trash.md` for the complete
transaction and interaction contract.

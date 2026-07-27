# Editorial Subsystem

Phase 3 provides a filesystem-native editorial workflow.

## Boundaries

`DraftEditor` creates, updates, and schedules drafts. `RevisionStore` captures immutable pre-change Markdown under `content/revisions/`. `Scheduler` selects due drafts without side effects. `Publisher` moves a validated draft into the dated post convention through an atomic destination write.

The Repository remains the read boundary. Editorial services are the only write boundary.

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

Passkey enrollment and login remain required before Phase 3 is complete. They must follow ADR 0008's WebAuthn verification contract; password authentication is not treated as a passkey fallback implementation.

# Editor Close and Content Trash

**Status:** Approved core design

## Purpose

The editor needs two distinct exits:

- **Close** preserves the current writing and returns to the authenticated
  Posts workspace.
- **Move to Trash** removes content from active publication while keeping it
  recoverable indefinitely.

Close is a persistence action. Trash is a canonical content lifecycle. Neither
action may silently discard Markdown.

## Scope

This design covers browser closing, draft deletion, published-article deletion,
Trash listing and restoration, and trusted-operator permanent purge. It does not
add published-article editing, automatic Trash expiry, bulk deletion, or remote
asset cleanup.

## Close interaction

The editor renders a quiet Close control in the upper outer margin, outside the
68ch writing measure. Its visibility is CSS-only:

- The control is dim by default.
- Hovering or focusing its margin reveal zone makes it legible.
- Leaving the zone hides it immediately.
- On touch, tapping the focusable margin zone reveals the control; activating
  the revealed control closes the editor.
- Keyboard focus always reveals it.
- Reduced-motion mode removes the opacity transition.

CSS owns only presentation. Activating Close uses the existing editor/autosave
JavaScript because a stylesheet cannot persist content or conditionally
navigate after a server response.

### Existing draft

Close writes the current recovery buffer and performs a synchronous autosave.
Only a successful server acknowledgement clears the matching local buffer and
redirects to `/posts`. Offline state, version conflict, authentication expiry,
or save failure leaves the editor open with an actionable message.

### New draft

Close creates the draft before navigating:

- When the first Markdown line yields a title, the normal derived title and
  unique slug rules apply.
- When no title can be derived, the canonical title is `Unsaved draft` and the
  base slug is `unsaved-draft`.
- Existing slug collision handling produces `unsaved-draft-2`,
  `unsaved-draft-3`, and so on.
- An empty body is allowed for this Close-created placeholder draft.

Close never uses `beforeunload` as its persistence mechanism. Browser-level
tab closing remains protected only by the recovery buffer; the explicit Close
control is the guaranteed save-and-return path.

## Content Trash architecture

`Katakata\Editorial\ContentTrash` is the only service allowed to trash or
restore canonical posts and drafts. Routes and commands pass resolved content
objects to it; they do not move or unlink Markdown directly.

Trash lives outside Repository discovery:

```text
content/trash/
  drafts/
    <trash-id>.md
    <trash-id>.json
  posts/
    <trash-id>.md
    <trash-id>.json
```

The `.md` file preserves the source bytes exactly. Its flat JSON manifest
contains:

```json
{
  "id": "20260824T121314123456Z-a1b2c3d4e5f6",
  "type": "post",
  "slug": "example",
  "original_path": "posts/2026/08/260824_example.md",
  "trashed_at": "2026-08-24T12:13:14+00:00",
  "actor_id": "account-id",
  "reason": null,
  "sha256": "content-hash"
}
```

IDs combine a UTC timestamp with a content hash suffix. Manifest paths are
relative to the configured content root. Restore validates the type, path
shape, and resolved destination root; a manifest can never select an arbitrary
filesystem path.

### Trash transaction

Before trashing, the service captures a normal immutable revision. It then:

1. rejects a missing source or an existing Trash ID;
2. atomically writes the exact Markdown bytes into Trash;
3. atomically writes the manifest;
4. verifies the stored checksum;
5. removes the source Markdown.

If any step before source removal fails, the source remains active and partial
Trash artifacts are rolled back. If source removal fails, the Trash artifacts
are removed and the action fails. Repository caches refresh only after the
service succeeds.

### Restore transaction

Restore verifies the manifest and checksum, refuses to overwrite an occupied
original path, writes the canonical destination atomically, verifies it, then
removes the Trash Markdown and manifest. Failure leaves the recoverable Trash
copy intact. Restoring a post re-establishes the same public URL because its
original dated path and Markdown bytes are preserved.

Trash retention is indefinite. Nothing expires automatically.

## Authorization and routes

All browser mutations require authentication, CSRF validation, and POST.

| Action | Editor | Owner/admin |
|---|---:|---:|
| Close and save a working draft | Yes | Yes |
| Trash a draft | Yes | Yes |
| Restore a draft | Yes | Yes |
| Trash a published article | No | Yes |
| Restore a published article | No | Yes |
| Permanently purge | No browser action | Trusted operator CLI only |

Proposed routes:

```text
POST /editor/drafts/{slug}/trash
POST /editor/posts/{slug}/trash
GET  /posts?status=trash
POST /editor/trash/{type}/{id}/restore
```

Published article slugs are resolved through the Repository and the service
acts on the resolved canonical path. Unknown, already-trashed, mismatched-type,
or unauthorized targets do not disclose filesystem details.

## Dashboard and warning behavior

Draft deletion appears in the editor's Post settings, visually separated from
routine save and publish actions. The disclosure names the draft and explains
that it will move to recoverable Trash.

Published deletion is available only in the authenticated `/posts` workspace
for owner/admin accounts. Its confirmation warns that the public URL, Home and
Archive listings, feeds, author page, article navigation, and discussion entry
point disappear until restoration. The action says **Move to Trash**, never
merely **Delete**.

`/posts?status=trash` lists title, type, deletion time, and actor without
rendering raw filesystem paths. Restore is explicit and reports destination
conflicts without replacing active content.

## Permanent purge

There is no browser purge action. A trusted deployment operator may run:

```bash
php bin/katakata trash:purge <type> <trash-id> --confirm=<trash-id>
```

The CLI requires the exact ID twice, verifies the manifest and path boundary,
and removes only that Markdown/manifest pair. Application account roles do not
authenticate local shell access; operational access control remains the host's
responsibility.

## Error handling

- Save-before-close failure: remain in the editor and retain the recovery
  buffer.
- Concurrent edit conflict: remain open and use the existing conflict message.
- Trash failure: keep source content active and show a non-path-bearing error.
- Restore destination collision: keep the Trash item and identify the occupied
  title/slug, not its absolute path.
- Malformed manifest or checksum mismatch: block restore and purge; require
  operator investigation.

## Upstream and downstream ownership

This is a Kata-kata core capability. Its service, routes, UI, CLI, generic
documentation, and synthetic tests land in `willio/katakata` first. A private
publication adopts an exact reviewed Kata-kata release candidate and verifies
the behavior against its own content without copying publication content or
operational state upstream.

Publication-specific branding, warning-copy refinements, deployment policy,
and real-content acceptance evidence remain downstream overlays. Generic fixes
found during dogfooding return to Kata-kata before the downstream advances to a
new exact upstream release.

## Verification

Unit coverage must prove exact-byte preservation, revision capture, rollback,
checksum validation, path-boundary validation, collision refusal, indefinite
retention, restoration, and exact-ID purge.

Feature coverage must prove CSRF and role boundaries, public disappearance and
reappearance, feed/archive/home exclusion, generic `unsaved-draft` collision
handling, save acknowledgement before redirect, and no navigation after failed
save.

Browser coverage at desktop and 320px must prove the CSS reveal zone, keyboard
focus visibility, touch two-step reveal, reduced-motion behavior, warning copy,
Trash listing, and restored navigation.

Downstream acceptance must additionally prove the same URL restoration against
representative real publication content without committing that content to the
upstream repository.

## Deliberate omissions

- No scheduled Trash expiry.
- No bulk trash or restore.
- No browser permanent purge.
- No deletion of discussion, analytics, mail, or distribution records. Those
  systems retain their existing privacy and retention policies.
- No published-article editor.

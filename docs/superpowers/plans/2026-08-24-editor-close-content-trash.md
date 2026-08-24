# Editor Close and Content Trash Implementation Plan

> **Execution contract:** implement in Kata-kata core first, with focused tests after each task, then adopt the reviewed commit downstream in Kamantara.

**Goal:** Add a guaranteed save-and-close path for the Markdown editor and an indefinitely recoverable, checksum-verified Trash lifecycle for drafts and published posts.

**Architecture:** `ContentTrash` is the single canonical move/restore boundary. It stores exact Markdown bytes plus a validated JSON manifest outside repository discovery, captures a revision before removal, and refreshes the repository only after successful route actions. The editor reuses the existing autosave request contract for Close; blank new drafts receive the collision-safe `unsaved-draft` identity.

**Stack:** PHP 8.5, framework-light router/container, Markdown files, PHPUnit 11, existing editor autosave JavaScript and CSS.

---

### Task 1: Content Trash domain

**Files:**
- Create: `app/Editorial/TrashItem.php`
- Create: `app/Editorial/ContentTrash.php`
- Test: `tests/Unit/Editorial/ContentTrashTest.php`

Implement listing, trash, restore, checksum/path validation, rollback, exact-byte preservation, revision capture, collision refusal, and exact-ID purge. Tests use temporary content roots and injected `AtomicFile`/`RevisionStore`.

### Task 2: Bootstrap and authenticated routes

**Files:**
- Modify: `bootstrap/app.php`
- Modify: `routes/editor.php`
- Test: `tests/Feature/ContentTrashRouteContractTest.php`

Register `ContentTrash`. Add POST draft/post trash and restore routes, CSRF checks, editor versus owner/admin authorization, non-path-bearing errors, repository refresh, and `status=trash` support.

### Task 3: Posts and editor presentation

**Files:**
- Modify: `resources/views/posts.php`
- Modify: `resources/views/editor.php`
- Modify: `public/assets/css/boundary.css`
- Modify: `public/assets/css/posts.css`
- Test: `tests/Feature/EditorTrashPresentationContractTest.php`

Add the Trash filter/list/restore actions, owner-only published controls and explicit warnings, draft Trash disclosure in Post settings, and the CSS-only close reveal zone with keyboard/touch/reduced-motion behavior.

### Task 4: Save-and-close behavior

**Files:**
- Modify: `routes/editor.php`
- Modify: `public/assets/js/editor.js`
- Modify: `public/assets/js/editor-autosave.js` only if the existing public binding lacks a close hook
- Test: `tests/Feature/EditorCloseContractTest.php`

Use the existing autosave endpoint and recovery buffer. Close waits for acknowledgement before redirecting. For a blank new document, derive `Unsaved draft` and the collision-safe `unsaved-draft` slug; failure keeps the editor open.

### Task 5: Trusted purge command and documentation

**Files:**
- Modify: `app/Console/Application.php`
- Create or modify: console command wiring near existing commands
- Modify: `docs/subsystems/editorial.md`
- Test: focused console test near existing console tests

Add `trash:purge <type> <id> --confirm=<id>` with exact-ID confirmation and no browser equivalent. Document indefinite retention and operational recovery.

### Task 6: Verification and downstream adoption gate

Run focused tests, `composer test`, `php bin/katakata content:validate`, and `git diff --check`. Commit coherent slices. Do not tag a public release or copy private Kamantara content upstream. Kamantara adopts only the exact reviewed Kata-kata commit after its own real-content URL restoration check.

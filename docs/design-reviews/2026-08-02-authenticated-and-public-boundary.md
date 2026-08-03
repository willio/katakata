# Authenticated and Public Boundary Review

Date: 2026-08-02  
Issues: #33, #34  
Branch: `agent/mail-fullscreen-composer-plan`

## Objective

Create a calm, typography-first boundary between Katakata’s signed-out publication surface and authenticated owner workspace. Each screen should identify itself once, expose detail only where the user can act on it, and keep deployment topology outside normal owner-facing views.

## Implemented contracts

### Login

- Password sign-in remains the primary form.
- Passkey is a compact alternative rather than a second full form.
- Passkey sign-in reuses the email entered in the primary form.
- The email field is focused when passkey sign-in is attempted without an email.
- Unsupported browsers do not show an unusable passkey action.
- The page states: `Private access for the publication team.`

### Dashboard

- Four linked summary cards retain their existing navigation roles.
- Recent visits are capped at five.
- Analytics has a quiet `View analytics` destination.
- Buzz is omitted when discussion is unavailable.
- Owner email and name are not repeated in the hero.

### Posts

- The title is the only row link for draft, scheduled, and published states.
- Rows have no browser list markers and use hairline separators.
- Desktop rows place title plus `status · author` on the left and date on the right.
- Narrow rows stack the date beneath metadata.
- Draft count excludes scheduled drafts; Scheduled remains a separate filter.
- Filters remain whole controls and may scroll horizontally at narrow widths.

### Mail workspace

- Desktop uses explicit sidebar, center-list, and reader columns.
- Panels have independent padding, separators, and scrolling behavior.
- Inbox account entries are visually subordinate to Inbox.
- Message rows use subject, sender/source metadata, then time.
- `Get new mail` remains a secondary action beside the active Inbox title.
- Message selection preserves the active account filter.
- Selected messages render server-side; JavaScript enhances selection and history only.
- With no selected item, the reader shows only `Select a message.`
- Compose, reply, and campaign drafting use focused fullscreen editors.
- At narrow widths, navigation, list, and detail become successive usable regions rather than compressed columns.

### Focused correspondence and campaign editors

- Correspondence and campaign drafts reuse the shared autosave protocol.
- Optimistic versions and conflict recovery remain authoritative.
- Correspondence send deletes only the exact version sent, preserving newer autosaves.
- Narrow correspondence actions remain reachable as a compact sticky action group.

### Settings

- Normal Reader inbox states use `Available`, `Waiting for setup`, `Needs attention`, and `Paused`.
- Normal Settings does not display server hosts, ports, mailbox folders, cache paths, commands, secret-reference names, or connection diagnostics.
- Mailbox account edits expose publication-facing tasks only: rename, pause/resume, and remove.
- Renaming an inbox preserves its private connection configuration in the server-side account record.
- The desktop folio is vertical; narrow navigation is horizontally scrollable.
- Editable sections use one readable field column and local Save actions.
- Placeholder Appearance, Account & Security, and System sections remain hidden.

## Preserved safety boundaries

- HTTP requests do not open IMAP connections.
- Mail rendering reads private operational cache only.
- Credentials remain environment/secret-manager values.
- Secret values and secret-reference names are not shown in normal Settings.
- Cached correspondence remains text-only; attachment payloads are not persisted.
- Mailbox local actions do not mutate the remote mailbox.

## Verification matrix

The following should be checked after pulling the branch.

| Route/state | Desktop | 320 px | Required check |
|---|---:|---:|---|
| `/login` | Yes | Yes | Primary password form; compact passkey alternative |
| `/dashboard` | Yes | Yes | Four cards; five visits maximum; no empty Buzz |
| `/posts` | Yes | Yes | Marker-free rows; title-only links; whole filters |
| `/mail?area=inbox` | Yes | Yes | Three panels; empty reader; no overflow |
| selected cached message | Yes | Yes | Server-rendered reader; active filter preserved |
| `/mail?area=campaigns` | Yes | Yes | List/detail hierarchy; no unnecessary empty scaffold |
| correspondence draft editor | Yes | Yes | return/save/send reachable; no horizontal overflow |
| campaign draft editor | Yes | Yes | focused editor; review/confirm preserved |
| `/dashboard/settings` | Yes | Yes | product language; scrollable narrow folio |
| reader inbox management | Yes | Yes | no deployment topology; rename preserves private config |

## Automated checks

Run:

```bash
php -l routes/settings-mailboxes.php
php -l resources/views/dashboard-settings-mailboxes.php
php -l resources/views/dashboard-settings.php
php -l resources/views/dashboard.php
php -l resources/views/posts.php
php -l resources/views/auth.php
php -l resources/views/mail.php
php -l tests/Feature/SettingsMailboxOwnerRenameContractTest.php
phpunit \
  tests/Feature/MailWorkspaceNavigationContractTest.php \
  tests/Feature/PostsBoundaryDesignContractTest.php \
  tests/Feature/SettingsMailboxReadinessContractTest.php \
  tests/Feature/SettingsMailboxOwnerRenameContractTest.php \
  tests/Feature/DashboardBoundaryDesignContractTest.php \
  tests/Feature/AuthBoundaryDesignContractTest.php
composer test
```

## Remaining manual evidence

Automated source and route contracts cannot prove final browser layout. Before closing #33 and #34, visually verify the matrix above in an authenticated browser at desktop and 320 px, including empty and selected Mail states.

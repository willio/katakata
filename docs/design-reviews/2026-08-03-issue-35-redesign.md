# Issue 35 Redesign Review

Date: 2026-08-03  
Issue: #35  
Branch: `issue/35-redesign`  
PR: #36

## Objective

Unify Katakata’s public publication, owner workspace, and focused writing surfaces without replacing the product and safety contracts already implemented.

The redesign keeps three distinct modes:

1. **Public publication** — serif-led, open, and mostly container-free.
2. **Owner workspace** — compact sans-serif interface with hairlines and selective surfaces.
3. **Focused editor** — fullscreen writing environment without surrounding dashboard navigation.

## Implemented slices

### Public homepage hybrid

- The latest article remains distinct without becoming an oversized magazine hero.
- Recent writing uses compact chronological rows with optional excerpts and author metadata.
- Archive, Newsletter, search, feeds, and earlier-edition navigation remain visible.
- The page remains editorial rather than becoming a card grid.

### Dashboard progression

- Empty publications receive one primary onboarding action and a concise getting-started sequence.
- Four linked summary metrics remain available in both empty and mature states.
- Mature publications show recent drafts, latest posts, and at most five recent visits.
- Empty Buzz, map, ranking, and analytics modules do not reserve space.

### Mail workspace

- Desktop remains `sidebar | active list | reader/detail`.
- Inbox rows now expose sender and time, subject, source mailbox, and a text snippet.
- Unread state uses typography and a structural accent marker rather than color alone.
- Selected rows use a restrained tonal surface.
- Campaign rows use compact state badges while preserving review and queue safety.
- Server-rendered selected-message content remains authoritative; JavaScript only enhances history and selection.

### Focused editors

- Correspondence and campaign editors share one header, writing measure, paper treatment, status placement, and action rhythm.
- Autosave, optimistic versions, conflict recovery, local recovery, and exact-version send deletion remain unchanged.
- Campaign delivery remains `review → confirm → queue`; no direct send action was introduced.
- Narrow actions remain sticky and reachable.

### Owner-route tokens

- Normal controls use one restrained `6px` radius.
- Full pills are reserved for compact filters and state badges.
- Keyboard focus is visible across owner routes.
- Posts retains title-only row navigation.
- Reader inbox deletion still requires typed confirmation.
- Login remains password-first with a compact passkey alternative.

## Intentional differences from the mocks

- Mail remains three-panel on desktop rather than becoming a two-column email client.
- Reply remains a focused editor rather than an inline textarea.
- Campaigns do not expose `Send now`; delivery retains review, late audience resolution, confirmation, and queueing.
- Dashboard modules are conditional rather than displaying permanent empty cards.
- Public pages remain mostly container-free.

## Responsive verification matrix

| Route/state | Desktop | 320 px | Required evidence |
|---|---:|---:|---|
| `/` with published posts | Required | Required | Featured latest, recent rows, no overflow |
| `/` empty | Required | Required | Concise empty publication state |
| `/login` | Required | Required | Password primary, passkey alternative, visible focus |
| `/dashboard` empty | Required | Required | One primary action, four metrics, getting started |
| `/dashboard` mature | Required | Required | Open sections, no empty modules, five visits maximum |
| `/posts` | Required | Required | Title-only rows, whole filters, stacked dates |
| `/mail?area=inbox` | Required | Required | Three columns desktop; destination/list progression narrow |
| selected cached message | Required | Required | Server-rendered reader, filter preserved |
| `/mail?area=campaigns` | Required | Required | Concise empty reader and status hierarchy |
| correspondence editor | Required | Required | Focused shell, autosave status, sticky actions |
| campaign editor | Required | Required | Focused shell, review/confirm/queue, recovery state |
| `/dashboard/settings` | Required | Required | One field column, scrollable narrow folio |
| reader inbox management | Required | Required | Product language, typed removal confirmation |

## Automated verification

```bash
php -l resources/views/home.php
php -l resources/views/dashboard.php
php -l resources/views/mail.php
php -l resources/views/mail-draft-editor.php
php -l resources/views/mail-campaign-draft.php
php -l tests/Feature/HomeRedesignContractTest.php
php -l tests/Feature/DashboardProgressiveRedesignContractTest.php
php -l tests/Feature/MailEditorialListRedesignContractTest.php
php -l tests/Feature/FocusedMailEditorRedesignContractTest.php
php -l tests/Feature/OwnerVisualTokenContractTest.php
php -l tests/Feature/Issue35ResponsiveMatrixContractTest.php
vendor/bin/phpunit \
  tests/Feature/HomeRedesignContractTest.php \
  tests/Feature/DashboardProgressiveRedesignContractTest.php \
  tests/Feature/MailEditorialListRedesignContractTest.php \
  tests/Feature/FocusedMailEditorRedesignContractTest.php \
  tests/Feature/OwnerVisualTokenContractTest.php \
  tests/Feature/Issue35ResponsiveMatrixContractTest.php
composer test
```

## Merge gate

PR #36 is not ready to merge until:

- the complete PHPUnit suite passes;
- the browser matrix above is checked at desktop and 320 px;
- no stale source-string assertion remains;
- no autosave, send concurrency, queueing, progressive-enhancement, or mailbox-safety regression is found;
- screenshots or equivalent visual evidence are attached to the review or PR.

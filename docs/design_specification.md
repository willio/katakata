# Katakata
## Design Specification

> **Status:** Canonical design reference for the Renderer (Phase 2)
> and Editor (Phase 3).
>
> This document derives from `docs/MASTER_SPECIFICATION.md`'s
> "Reader Experience" and "Writer Experience" sections. It does not
> introduce new architecture — see the relevant ADRs for that — it
> defines how the existing architecture should look and feel.

## Philosophy

The interface is not the product. The writing is.

Every design decision optimizes for the software disappearing behind the
words. Every visible element must justify its existence. If a reader or
writer notices the interface before the content, the design has failed.

## Reading Experience

### Layout

- One column, with no article sidebar.
- Enforce a 60–75 character measure with `ch` units.
- Use a body line-height around 1.6 and at least one full line-height
  between paragraphs.
- Never place autoplay, interstitials, or blocking prompts above the fold.

### Typography

Reading and interface typography use deliberately different voices:

| Role | Typeface family | Rationale |
|---|---|---|
| Article body | Serif | Long-form reading comfort |
| Site chrome | Sans-serif | Fast scanning at small sizes |
| Editor | Monospace | A stable, deliberate writing tool |

Decided exception: mail message body text in the owner Mail workspace renders
in the sans UI voice — a deliberate owner-UI exception to serif-for-reading,
keeping correspondence visually part of the interface rather than the
editorial reading surface.

```css
--font-serif: "Source Serif 4", "Charter", Georgia, serif;
--font-sans: "Inter", -apple-system, "Segoe UI", sans-serif;
--font-mono: "iA Writer Mono", "JetBrains Mono", "SF Mono", Menlo, monospace;
```

Fonts are self-hosted or system-provided, never render-blocking external
requests. Source Serif 4 remains Katakata’s editorial serif; no new font
dependency is introduced. Public display expression comes from scale, measure, weight, rhythm, and restrained italics, not a replacement typeface. Public body copy is at least 19px.

### Public editorial roles

- Home lead titles may use large serif display text with rare italic emphasis.
- Article titles are serif-led but quieter than the Home lead, capped by
  measure and scale.
- Archive and author entry titles use the editorial serif; years, labels,
  dates, and navigation use the sans-serif stack.
- Newsletter is a public editorial conversion surface, not an owner
  authentication surface, even when it shares field primitives.

### Chrome

Reader chrome contains only:

- One consistently positioned site name or mark.
- A quiet serif-led article title with a sans-serif date and author block.
- A minimal footer containing an author bio, a Threads continuation when
  one exists, and previous/next or archive navigation.

Search, tag clouds, related-post grids, and floating share buttons are absent
unless a later subsystem explicitly justifies them.

### Icons

Tabler Icons illustrate function and never decorate. They inherit
`currentColor`, match surrounding cap height, and accompany actions only.

## Color System

Content ink remains high-contrast in both modes. `--accent` is reserved for
links and active states; `--katakata` is reserved for rare editorial identity
emphasis.

### Light mode

| Token | Value | Use |
|---|---|---|
| `--bg` | `#FAF9F6` | Warm page background |
| `--surface` | `#F3F1EC` | Recessed areas and code |
| `--ink` | `#2B2A27` | Body text |
| `--ink-muted` | `#6B6963` | Secondary text |
| `--border` | `#E4E1D8` | Hairlines |
| `--accent` | `#3D6E5C` | Links and active states |
| `--katakata` | `#BF5A43` | Rare editorial identity emphasis |

### Dark mode — Nord

| Token | Value | Use |
|---|---|---|
| `--bg` | `#2E3440` | Page background |
| `--surface` | `#3B4252` | Recessed areas and code |
| `--border` | `#4C566A` | Hairlines |
| `--ink-muted` | `#D8DEE9` | Secondary text |
| `--ink` | `#ECEFF4` | Body text |
| `--accent` | `#88C0D0` | Links and active states |

Use `prefers-color-scheme` by default. A future manual override remains
client-side presentation state. Whether it uses `localStorage` or a cookie is
an open decision and must not be chosen accidentally.

## Writing Experience

### Core surface

The editor is a fullscreen text surface:

- Centered at 60–75ch, monospace, with line-height around 1.7.
- No formatting toolbar; writers type Markdown directly.
- Routine saving is invisible. Only offline, failed-save, recovery, and validation warnings surface.
- Title, slug, metadata, publishing, accounts, and other controls remain in
  a settings panel hidden until explicitly summoned.

The reading and writing experiences are intentionally different rooms in the
same house: serif for reading, monospace for writing.

### Focus mode

Current-paragraph or current-sentence focus is a candidate for Phase 3, not a
requirement. It remains deferred until Markdown-aware boundary behavior is
specified.

### Save state

`localStorage` is a recovery buffer, not the source of truth. The server-side
Markdown file is authoritative.

1. After each keystroke, debounce a draft-specific local buffer by roughly
   500ms–1s.
2. Synchronize to the server every 5–10 seconds and on blur or visibility
   change.
3. Keep routine `Saving…` and `Saved` states invisible. Surface only actionable exceptions such as `Not saved — offline` or `Save failed`.
4. If the browser holds a newer buffer than the server response, compare a
   monotonic timestamp/version and prompt the writer to reconcile. Never
   silently overwrite either side.
5. Remove the local buffer only when the server confirms receipt of that exact
   version.

Two tabs or devices editing one draft remain a last-write-wins limitation in
v1. CRDT or operational-transform collaboration is deferred.

### Derived post identity

- The first line of the Markdown body is the post title. A leading Markdown heading marker is ignored.
- New-draft slugs are generated automatically from the derived title.
- A published or already-created draft keeps its existing slug so editing a title never breaks its canonical URL.
- Title and slug are visible as read-only confirmation in per-post settings, not separate writing tasks.

### Controls and warnings

- Fields use a dotted bottom border only, with no box or background.
- Text buttons render as slim, wide controls whose vertical padding is
  substantially smaller than their horizontal padding. Icon-only close controls
  remain circular.
- Action rows provide deliberate separation from fields and surrounding
  content; buttons never collide with field underlines or container edges.
- Routine persistence has no permanent status label.
- Actionable warnings render as dim, text-only toasts in the bottom-left. They never cover the writing line or demand dismissal unless a choice is required.
- Settings retain an explicit close button and close with Escape.
- `docs/fields-buttons-styleguide.md` is the implementation contract for
  field states, clear controls, button tokens, proportions, spacing, and
  accessibility.

### Dashboard

The authenticated dashboard follows the same calm interface rule. It is an orientation surface, not a second editor: one slim header, a fixed information hierarchy, no customizable widget grid, and no decorative charting. See `docs/subsystems/dashboard.md`.

### Editor icons

Icons remain functional only: save state, settings, and exit are the expected
order of magnitude. Formatting remains Markdown syntax rather than toolbar
buttons.

## Deliberate Omissions

- No component library is specified here.
- No collaborative real-time editing.
- No focus-mode implementation contract.
- No JavaScript framework. Vanilla JavaScript with `fetch` and `localStorage`
  is the default until a concrete need justifies a separate ADR.

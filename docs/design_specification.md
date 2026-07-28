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

```css
--font-serif: "Source Serif 4", "Charter", Georgia, serif;
--font-sans: "Inter", -apple-system, "Segoe UI", sans-serif;
--font-mono: "iA Writer Mono", "JetBrains Mono", "SF Mono", Menlo, monospace;
```

Fonts are self-hosted or system-provided, never render-blocking external
requests. Body copy is at least 19–20px at typical viewport widths.

### Chrome

Reader chrome contains only:

- One consistently positioned site name or mark.
- A quiet sans-serif article title, date, and author block.
- A minimal footer containing an author bio, a Threads continuation when
  one exists, and previous/next or archive navigation.

Search, tag clouds, related-post grids, and floating share buttons are absent
unless a later subsystem explicitly justifies them.

### Icons

Tabler Icons illustrate function and never decorate. They inherit
`currentColor`, match surrounding cap height, and accompany actions only.

## Color System

Content ink remains high-contrast in both modes. Accent color is reserved for
links and active states.

### Light mode

| Token | Value | Use |
|---|---|---|
| `--bg` | `#FAF9F6` | Warm page background |
| `--surface` | `#F3F1EC` | Recessed areas and code |
| `--ink` | `#2B2A27` | Body text |
| `--ink-muted` | `#6B6963` | Secondary text |
| `--border` | `#E4E1D8` | Hairlines |
| `--accent` | `#3D6E5C` | Links and active states |

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
- One permanent textual save-state element.
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
3. Show honest text: `Saving…`, `Saved`, `Not saved — offline`, or
   `Save failed`.
4. If the browser holds a newer buffer than the server response, compare a
   monotonic timestamp/version and prompt the writer to reconcile. Never
   silently overwrite either side.
5. Remove the local buffer only when the server confirms receipt of that exact
   version.

Two tabs or devices editing one draft remain a last-write-wins limitation in
v1. CRDT or operational-transform collaboration is deferred.

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

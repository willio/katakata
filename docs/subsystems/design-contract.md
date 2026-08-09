# Design Contract Implementation

The canonical visual and interaction contract is `docs/design_specification.md`.
This subsystem note records implementation choices without replacing that contract.

## Public and owner roles

Source Serif 4 remains Katakata’s editorial serif; public display expression
comes from scale, measure, weight, rhythm, and restrained italics rather than
a new font dependency. Home lead titles may be large serif display text with
rare italic emphasis. Article titles are serif-led but quieter than the Home
lead, capped by measure and scale. Archive and author entry titles use the
editorial serif, while years, labels, dates, and navigation use sans-serif.

Newsletter is a public editorial conversion surface, not an owner
authentication surface, even where it shares field primitives. Normal owner
controls use a 6px radius; full pills are reserved for compact filters and
state badges. The canonical colour vocabulary is `--bg`, `--surface`, `--ink`,
`--ink-muted`, `--border`, `--accent`, and `--katakata`.

## Reader

`public/assets/css/site.css` exposes the canonical light and Nord dark tokens and
uses `prefers-color-scheme` without an external font request. Reading surfaces are
limited to `68ch`, body copy uses the serif stack, and orientation chrome uses the
sans-serif stack. Article footers provide author and archive orientation only.

The manual color-mode override remains intentionally unimplemented because the
design specification leaves its persistence mechanism open. It must not be added
until that choice is made explicitly.

## Editor

The editor is a fullscreen `68ch` monospace writing surface. Metadata, draft
navigation, publishing, invitations, passkeys, and account controls remain hidden
in the settings panel until the writer opens it. `Cmd/Ctrl+,` toggles the panel.

Existing drafts buffer each edit in `localStorage`, then synchronize to the server
after seven seconds, on focus loss, and when the document becomes hidden. The
status text distinguishes `Saving…`, `Saved`, `Not saved — offline`, and
`Save failed`.

The browser buffer records the server version it began from plus its own timestamp
and client version. A newer local buffer is never applied silently. Once the
server confirms the exact client version, that buffer is removed.

Concurrent tabs and devices remain last-write-wins for v1. This is an explicit
limit, not a claim of collaborative editing.

## Owner presentation layers

Owner and owner-authentication views load `site.css`, then `boundary.css`, then
any route-specific stylesheet. The boundary layer owns the canonical 6px
control radius and visible focus outline. Route stylesheets retain only their
layout, responsive, sticky-action, panel, and state-badge responsibilities.

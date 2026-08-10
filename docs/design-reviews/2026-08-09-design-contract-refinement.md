# Design Contract Refinement Review

Date: 2026-08-09
Branch: `design/design-contract-refinement`
Reviewed range: `d56e600..8f9943f`

## Decision

The refined design contract is accepted. Katakata keeps **Source Serif 4** as
its editorial serif. Its public character comes from hierarchy, measure,
weight, rhythm, and scarce red-orange editorial emphasis—not from a new font,
card system, or decorative treatment.

The implementation continues to distinguish three rooms:

1. **Public publication** is serif-led, open, and content-first.
2. **Owner workspace** is compact, sans-serif, and functional, with hairlines,
   restrained surfaces, 6px controls, and visible focus.
3. **Focused writing and correspondence** remove surrounding workspace chrome;
   Markdown remains monospace, while correspondence and campaigns retain a
   readable serif writing measure.

## Final type-role matrix

| Surface or role | Typeface role | Hierarchy and boundary |
|---|---|---|
| Home lead | Editorial serif | The only large display title; rare italic and `--katakata` emphasis are permitted. |
| Article title and body | Editorial serif | Quieter than the Home lead; body copy is at least 19px and stays within the reading measure. |
| Archive and author entries | Editorial serif | Entry titles carry the editorial hierarchy; years, labels, dates, and navigation remain sans-serif. |
| Newsletter | Public editorial serif plus shared sans controls | A conversion surface, not an owner-authentication surface. |
| Public metadata and chrome | Neutral sans-serif | Orientation and interaction only; it does not compete with reading. |
| Owner workspace headings and controls | Neutral sans-serif | Compact utility hierarchy; normal controls use 6px corners and pills remain limited to filters and state badges. |
| Markdown editor | Monospace | Fullscreen focused writing surface with a 68ch measure. |
| Correspondence and campaign editors | Editorial serif body with sans controls | Focused rooms with reachable narrow actions and unchanged review/confirm/queue safety flow. |

## Visual-review record

The final browser matrix captured every route/state below at **1440 x 1000**
and **320 x 844** using approved disposable fixtures. The review considered
the light and Nord-dark token treatments together: content contrast, hierarchy,
and restrained surfaces remain distinct without adding a second visual
signature. The raw full-page captures are disposable test output; this record
is the durable evidence summary.

| Family | Routes and states checked | Desktop outcome | 320px outcome |
|---|---|---|---|
| Public | Home, login, article, archive, author, newsletter | Serif public titles, loaded styles, and bounded reading/form measures; Home retains its deliberately wider editorial index. | No horizontal overflow or clipped page width; long Indonesian titles wrap in the column rather than forcing a horizontal scroll. |
| Owner orientation | Dashboard, Posts, Analytics, settings, mailbox management, mailbox import | Sans owner headings and route-specific layouts remain inside the shared `site.css` then `boundary.css` composition. | Controls and content stack without clipping; the settings folio remains a single-column, scrollable surface. |
| Mail workspace | Inbox, selected cached message, Mail archive, Sent Mail, campaign workspace, campaign history | The multi-panel workspace preserves its destination, list, and reader hierarchy; selected and state-badge treatments remain restrained. | Destination/list progression remains legible; the active Mail destination stays within its visible sidebar bounds and the reader flows below without horizontal overflow. |
| Focused rooms | Correspondence editor, campaign editor, Markdown editor with settings open | Writing surfaces retain their intended serif or monospace role, paper treatment, status placement, and action rhythm. | Sticky actions remain visually reachable, panels do not clip, and the open Markdown settings panel coexists with the writing surface without horizontal overflow. |

The 42 browser cases also asserted loaded stylesheets and horizontal-overflow
limits for every capture. Public secondary surfaces asserted a maximum 75ch
measure. Computed styles confirmed the serif public-title role, sans owner
headings, monospace Markdown editor, and serif correspondence/campaign bodies.

## Restraint pass

No visual treatment was removed. The checked surfaces do not accumulate more
than one signature device: public pages use editorial serif hierarchy with
scarce red-orange emphasis, while owner pages use functional sans-serif
controls, hairlines, and modest state treatment. Full pills remain confined to
compact filters and state badges.

## Deferred follow-up

The capture matrix proves layout, type roles, selected states, measure, and
overflow at desktop and 320px. It does not perform an interactive keyboard
traversal or measure touch-target hit areas. Visible owner focus styling is
covered by the shared boundary contract, but a future manual accessibility pass
should record focused controls and touch reachability across the same route
matrix.

## Verification evidence

- PHP suite: `344 tests, 1532 assertions` passed.
- Responsive browser matrix: `42/42` passed across desktop and 320px using
  approved disposable fixtures; focused editors blocked autosave writes.
- No production, remote-mail, canonical-content, or package state was mutated
  for the visual review.

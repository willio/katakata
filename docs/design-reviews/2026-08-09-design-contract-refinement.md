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

Task 5's controller ran the browser matrix at **1440 x 1000** and **320 x
844** using approved disposable fixtures. Task 6 did not rerun this matrix;
`42/42` is inherited Task 5 controller evidence. Each case generated a
full-page Playwright screenshot, but those captures were not retained as
durable review evidence or manually inspected for Task 6.

| Family | Routes and states exercised | Assertions at both viewports |
|---|---|---|
| Public | Home, login, article, archive, author, newsletter | Loaded stylesheets, document-wide horizontal overflow, and computed serif font roles for the Home lead and public titles. Article, archive, author, and newsletter also had a maximum 75ch measure on the selected public secondary surfaces; Home was deliberately excluded. |
| Owner orientation | Dashboard, Posts, Analytics, settings, mailbox management, mailbox import | Loaded stylesheets, document-wide horizontal overflow, and computed sans font roles for `h1` headings. |
| Mail workspace | Inbox, selected cached message, Mail archive, Sent Mail, campaign workspace, campaign history | Loaded stylesheets and document-wide horizontal overflow. At 320px, the Inbox case also asserted that the active Mail destination remained within its sidebar bounds. |
| Focused rooms | Correspondence editor, campaign editor, Markdown editor with settings open | Loaded stylesheets and document-wide horizontal overflow. Computed font-role comparisons covered serif correspondence and campaign bodies and the monospace Markdown textarea; the settings panel was asserted visible before its editor checks. |

Here, loaded stylesheets means the browser exposed at least one stylesheet and
each exposed stylesheet had a nonzero accessible rule count. Document-wide
horizontal overflow means `documentElement` and `body` scroll widths were at
most one pixel wider than the viewport. These are layout and computed-style
assertions; they do not establish visual hierarchy, clipping of individual
controls, touch reachability, or the appearance of selected-state treatments.

## Restraint pass

No visual treatment was removed. The retained type and token contract continues
to specify one public signature—editorial serif hierarchy with scarce
red-orange emphasis—and reserves full pills for compact filters and state
badges. Because Task 6 did not manually inspect retained captures, this is a
contract decision rather than a visual conclusion about every exercised route.

## Deferred follow-up

Dark-mode visual assessment is deferred. The `42/42` matrix is viewport-only:
it did not emulate or assert `prefers-color-scheme: dark`, so it cannot support
a conclusion about Nord-dark contrast, hierarchy, or surfaces.

Long Indonesian-title wrapping and empty-state presentation are deferred. Task
5 has no assertion or dedicated fixture for either condition, and Task 6 has
no retained, manually inspected evidence for them. The selected cached-message
fixture is evidence only that a selected message route could be exercised; it
does not demonstrate selected-state styling or an empty reader.

An interactive keyboard and touch-target pass also remains deferred. Visible
owner focus styling is covered by the shared boundary contract, but a future
manual accessibility pass should retain and inspect evidence for focused
controls, touch reachability, dark mode, long Indonesian titles, empty states,
and selected states across the same route matrix.

## Verification evidence

- Original Task 6 PHP suite: `344 tests, 1532 assertions` passed. The
  documentation-correction suite later passed with `346 tests, 1541 assertions`.
- Task 5 controller browser matrix: `42/42` passed across desktop and 320px
  using approved disposable fixtures; Task 6 did not rerun it. Focused editors
  blocked autosave writes.
- No production, remote-mail, canonical-content, or package state was mutated
  for the visual review.

# Home Editorial Hierarchy

> Status: Accepted design specification
>
> Scope: `/home`
>
> Purpose: Define the typography, colour, contrast, scale, and spacing hierarchy for Katakata's editorial homepage.

---

# Design Intent

The homepage is an editorial front page, not a feed, dashboard, or marketing page.

Its visual character should sit between:

- the editorial confidence of *The New Yorker*
- the plain, literary directness of Ludic / Mataroa
- the personal immediacy of a classic Tumblr blog

The result must feel recognisably Katakata without copying another publication's visual language.

Katakata's identity is created through three coordinated systems:

1. Typography
2. Colour and contrast
3. Scale and spacing

No card system, widget language, or decorative chrome should compete with the writing.

---

# Homepage Structure

```text
Katakata                                      Newsletter  Archive

LATEST
Latest Post Title
Short summary or dek.
Listen · Read

Previous Post Title                   Author · 2026 02 03
Previous Post Title                   Author · 2026 01 15
Previous Post Title                   Author · 2025 12 28
Previous Post Title                   Author · 2025 12 11
Previous Post Title                   Author · 2025 11 30

2025 Edition →

Search editions

Footer
```

The lead story establishes tone. The previous-story rows form the current edition index. Archive, search, newsletter, and footer remain quiet secondary structures.

---

# Typography System

Use no more than three type voices:

1. Editorial serif
2. Neutral sans-serif
3. Optional compact utility face for labels or dates

The mix should feel intentional rather than ornamental.

## Recommended roles

### Editorial Serif

Use for:

- lead article title
- lead summary
- previous article titles
- selected archive headings

Characteristics:

- literary but not nostalgic
- strong italics
- moderate contrast
- readable at both display and text sizes
- not overly mannered

Preferred direction:

- Source Serif 4

Source Serif 4 remains Katakata’s editorial serif. Do not introduce a new
editorial font dependency; express the homepage display treatment through
scale, measure, weight, rhythm, and rare italic emphasis.

### Neutral Sans-Serif

Use for:

- Katakata wordmark
- navigation
- eyebrow labels
- author names
- dates
- interaction text
- footer text

Characteristics:

- calm
- compact
- clear at small sizes
- low personality compared with the editorial serif

Preferred direction:

- Inter
- IBM Plex Sans
- Manrope
- system sans stack

### Utility Face

Optional. Use only if the sans-serif cannot produce enough contrast.

Use for:

- dates
- edition labels
- microcopy

Preferred direction:

- IBM Plex Mono
- ui-monospace

The utility face must remain rare. Avoid a technical tone.

---

# Type Hierarchy

## Level 0 — Katakata Wordmark

Purpose: permanent publication identity.

- Typeface: neutral sans-serif
- Weight: 650–750
- Size: `1.05rem–1.2rem`
- Letter spacing: `-0.02em`
- Line height: `1`
- Colour: primary ink
- Behaviour: always visible in the header

The wordmark should feel typographic rather than logo-like.

## Level 1 — Lead Article Title

Purpose: dominant editorial focus.

- Typeface: editorial serif
- Weight: 600–750, depending on family
- Size: `clamp(2.75rem, 7vw, 5.75rem)`
- Line height: `0.92–1.02`
- Letter spacing: `-0.035em to -0.015em`
- Max width: `11–14ch`
- Colour: Katakata accent

Large serif display text is reserved for the Home lead. Italic emphasis is
rare and must not become a decorative default.

The title is the primary brand carrier. It should be visually distinctive enough that the page remains identifiable even when the wordmark is not prominent.

## Level 2 — Lead Summary / Dek

Purpose: explain the lead article without competing with the title.

- Typeface: editorial serif
- Weight: 400
- Size: `1.2rem–1.5rem`
- Line height: `1.45–1.6`
- Max width: `42–58ch`
- Colour: secondary ink

Limit to two or three short lines on desktop.

## Level 3 — Previous Article Titles

Purpose: form the current edition's table of contents.

- Typeface: editorial serif
- Weight: 500–650
- Size: `1.3rem–1.75rem`
- Line height: `1.15–1.3`
- Colour: primary ink

Previous titles must remain noticeably quieter than the lead title but more prominent than metadata.

## Article, Archive, and Author Titles

Article titles are serif-led but quieter than the Home lead, capped by measure
and scale. Archive and author entry titles use the editorial serif; years,
labels, dates, and navigation use the sans-serif stack.

## Level 4 — Navigation and Article Actions

Purpose: enable movement without introducing UI chrome.

- Typeface: neutral sans-serif
- Weight: 500–600
- Size: `0.9rem–1rem`
- Line height: `1.3`
- Colour: link ink

Examples:

- Newsletter
- Archive
- Listen
- Read
- Search editions

Use text links, not buttons, unless an action requires a control state.

## Level 5 — Eyebrow

Purpose: mark the lead story and reinforce Katakata identity.

- Typeface: neutral sans-serif
- Weight: 700
- Size: `0.72rem–0.8rem`
- Letter spacing: `0.12em–0.18em`
- Text transform: uppercase
- Colour: Katakata accent

The eyebrow and lead title share the accent colour. No other large text should use it by default.

## Level 6 — Metadata

Purpose: provide authorship and chronology.

- Typeface: neutral sans-serif or optional utility face
- Weight: 400–500
- Size: `0.75rem–0.875rem`
- Line height: `1.3`
- Colour: tertiary ink

Preferred date format:

```text
Author · 2026 02 03
```

Keep punctuation and separators minimal.

## Level 7 — Footer and Microcopy

Purpose: remain available without entering the reading hierarchy.

- Typeface: neutral sans-serif
- Weight: 400
- Size: `0.72rem–0.82rem`
- Line height: `1.45`
- Colour: tertiary ink

---

# Colour System

Use the canonical `--bg`, `--surface`, `--ink`, `--ink-muted`, `--border`,
`--accent`, and `--katakata` tokens. `--katakata` is the restrained
red-orange editorial identity accent; `--accent` remains the interaction
colour.

## Core palette

```css
:root {
    --bg: #FAF9F6;
    --surface: #F3F1EC;
    --ink: #2B2A27;
    --ink-muted: #6B6963;
    --border: #E4E1D8;
    --accent: #3D6E5C;
    --katakata: #BF5A43;
}
```

## Accent colour

Primary accent:

```text
var(--katakata)
```

Character:

- red-orange
- warm
- muted rather than saturated
- compatible with Nord's subdued temperature
- distinctive enough to become Katakata's editorial signature

Use for:

- eyebrow
- lead title
- selected active state
- rare editorial emphasis

Do not use for:

- body copy
- every link
- metadata
- rules
- background panels

The accent should be memorable because it is scarce.

## Contrast roles

### Primary ink

```text
var(--ink)
```

Use for:

- wordmark
- previous article titles
- body copy
- high-priority navigation

### Secondary ink

```text
var(--ink-muted)
```

Use for:

- summaries
- archive descriptions
- secondary editorial text

### Tertiary ink

```text
var(--ink-muted)
```

Use for:

- author/date metadata
- footer text
- quiet labels

### Rules

```text
var(--border)
```

Use sparingly between rows and major sections.

### Links

```text
var(--accent)
```

A restrained Nord-adjacent green separates interaction from editorial emphasis.

The accent red-orange marks identity. The green marks interaction.

---

# Contrast Requirements

- Standard body text must meet WCAG AA contrast against `--bg`.
- Metadata must not be lighter than needed for AA at its rendered size.
- Accent title colour must meet large-text contrast requirements.
- Never communicate active state through colour alone.
- Underlines should remain visible for inline links.

Avoid placing text directly on accent-coloured backgrounds on `/home`.

---

# Size and Spacing Hierarchy

Typography carries the primary hierarchy. Spacing supports it.

## Page width

```css
.home-shell {
    width: min(100% - 3rem, 58rem);
    margin-inline: auto;
}
```

Desktop target: `820–930px`.

Mobile horizontal padding: `20–24px`.

## Vertical rhythm

Use a base spacing unit of `0.5rem`.

Recommended scale:

```text
4px   micro alignment
8px   label-to-title
12px  metadata grouping
16px  compact row spacing
24px  title-to-summary
32px  row rhythm
48px  section spacing
72px  major separation
96px  page-level separation
```

## Header

- Top padding: `2rem–3rem`
- Bottom margin: `4rem–6rem`
- Wordmark and navigation share one baseline

## Lead story

- Eyebrow to title: `0.75rem`
- Title to summary: `1.5rem–2rem`
- Summary to actions: `1.25rem–1.75rem`
- Lead story to edition index: `4rem–6rem`

## Previous article rows

- Padding block: `1.1rem–1.5rem`
- One subtle bottom rule
- Title and metadata align to separate grid columns on desktop
- Stack on mobile

## Archive link

- Top margin: `3rem–4rem`
- Same visual weight as navigation, not as article titles

## Search

Search should appear as a quiet inline field or disclosure, not a boxed module.

- Top margin: `3rem–4rem`
- Bottom border only
- No filled background
- No oversized search icon

## Footer

- Top margin: `5rem–8rem`
- Thin top rule
- Compact layout

---

# Desktop Grid

Use a two-column editorial row:

```text
Article title                        Author · Date
```

Suggested proportions:

```css
.edition-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 2rem;
    align-items: baseline;
}
```

The metadata column should not set the visual width of the page.

---

# Mobile Behaviour

Below approximately `42rem`:

```text
Previous Post Title
Author · 2026 02 03
```

- Stack metadata beneath title
- Reduce lead title scale without compressing line height excessively
- Preserve generous section spacing
- Keep header navigation on one line when possible
- Do not convert rows into cards

---

# Interaction States

## Links

Default:

- green interaction colour
- underline or clearly visible text decoration

Hover/focus:

- darker green
- underline offset increases slightly

## Lead title

The lead title may be linked as a whole.

Default:

- accent colour
- no underline

Hover/focus:

- accent hover colour
- underline appears only if needed for accessibility

## Focus

All interactive elements require a visible focus treatment.

Preferred:

```css
outline: 2px solid var(--katakata);
outline-offset: 4px;
```

---

# Editorial Rules

- The homepage is the current edition, not an infinite feed.
- The lead article receives the strongest typographic and colour emphasis.
- Previous articles are rows, never cards.
- Newsletter subscription does not dominate `/home`.
- Search remains visually quiet.
- Metadata is functional, never decorative.
- Decorative icons should not be introduced unless they clarify an action.
- No shaded content panels on `/home`.
- No gradients.
- No drop shadows.
- No rounded cards.
- No display colour beyond the Katakata accent unless explicitly specified.

---

# Reference CSS Tokens

```css
:root {
    --font-serif: "Source Serif 4", "Charter", Georgia, serif;
    --font-sans: "Inter", -apple-system, "Segoe UI", sans-serif;
    --font-mono: "iA Writer Mono", "JetBrains Mono", "SF Mono", Menlo, monospace;

    --bg: #FAF9F6;
    --surface: #F3F1EC;
    --ink: #2B2A27;
    --ink-muted: #6B6963;
    --border: #E4E1D8;
    --accent: #3D6E5C;
    --katakata: #BF5A43;
}
```

---

# Acceptance Criteria

The `/home` implementation is conformant when:

- the page reads as an editorial front page rather than a product page
- type, colour, and spacing establish a clear hierarchy without cards
- the eyebrow and lead title use the Katakata accent
- interaction links use the separate link colour
- previous posts render as compact editorial rows
- metadata remains legible but quiet
- the newsletter no longer dominates the homepage
- search is reduced to a minimal editorial control
- desktop and mobile preserve the same hierarchy
- all text and interaction states meet accessibility contrast requirements

---

# Guiding Principle

> Katakata should be recognisable from its front page even before a reader notices the wordmark.
>
> The recognition comes from the relationship between editorial serif, warm paper, restrained metadata, and the red-orange Katakata accent.

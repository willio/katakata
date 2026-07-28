# Katakata
## Fields & Buttons — Component Styleguide

> **Status:** Canonical implementation contract for text fields and buttons.
>
> Extends `docs/design_specification.md`. Where this document is silent,
> that specification's typography, color, icon, and calm-interface rules apply.

## Fields

### Structure

- No box, background, or full border. At rest, a field uses only
  `border-bottom: 1px dotted var(--border)`.
- Fields sit inline in the surrounding form without a separate visible label
  row. The placeholder is the visible label and disappears when content is
  entered.
- Every field still requires an accessible name through a visually hidden
  `<label>` or an accurate `aria-label`; a placeholder alone is not an
  accessible label.
- Validation messages name the field, for example `Email is required`, never
  only `Required`.
- Reserve trailing padding for the clear control so field content cannot
  overlap it.

### Clear control

- Appears only while an enabled field contains content.
- Uses the Tabler `x` icon at the field text's cap height, in
  `currentColor`.
- Clears the field without confirmation and immediately restores focus to it.
- Has a field-specific accessible name, for example
  `aria-label="Clear email"`.
- Is absent for empty or disabled fields.

### States

| State | Border | Notes |
|---|---|---|
| Empty | `1px dotted var(--border)` | Placeholder visible |
| Focus | `1px dotted var(--accent)` | Color changes; line remains dotted |
| Filled | `1px dotted var(--border)` | Clear control visible |
| Error | `1px dotted var(--error)` | Named inline message below the field |
| Disabled | `1px dotted var(--border)` | `opacity: 0.5`; no clear or focus treatment |

Error messages are small sans-serif UI text and connect to their field with
`aria-describedby`.

### Error tokens

```css
:root {
  --error-light: #b3543f;
  --error: var(--error-light);
}

@media (prefers-color-scheme: dark) {
  :root {
    --error-dark: #bf616a;
    --error: var(--error-dark);
  }
}
```

The light token is muted terracotta. The dark token is Nord11.

### Typography

Fields use the sans-serif UI stack. A field remains interface even when it
collects a title, excerpt, or other content.

## Buttons

### Role

Buttons are reserved for committed actions such as Publish, Save, New Post,
and Clear. Navigation remains a link. Pill styling must not be applied to every
clickable element or the action hierarchy disappears.

### Shape and proportions

- Text buttons use a full pill: `border-radius: 999px`.
- Vertical padding is always substantially smaller than horizontal padding.
- The default proportion is approximately 1:3 so buttons render slim and wide,
  never fat.
- Icon-only buttons are the exception: they use equal width and height and
  remain true circles.

```css
button,
.button {
  padding: 0.55em 1.5em;
  border-radius: 999px;
}
```

### Color

| Mode | Background | Text |
|---|---|---|
| Light | `--btn-bg: #4C566A` | `--btn-ink: #ECEFF4` |
| Dark | `--btn-bg: #EBCB8B` | `--btn-ink: #2E3440` |

Button color is intentionally distinct from the link and active-state accent:
buttons signal actions; links signal navigation. The light pairing uses Nord3 with Nord6 ink; the dark pairing uses Nord13 with Nord0 ink. Both pairs must be verified
with an automated contrast check before release.

### States

| State | Treatment |
|---|---|
| Default | Mode-specific button tokens |
| Hover | Background approximately 8–10% darker in the same hue |
| Active | Background approximately 15% darker; no shadow or scale animation |
| Disabled | `opacity: 0.4`; no hover or active response |

If an icon is functionally necessary, it precedes the label, matches the text
cap height, and inherits `currentColor`.

## Action spacing

Button spacing belongs to the action row, not to individual buttons.

- Use `gap: 0.75rem` between actions.
- Keep at least `1.25rem` above and below an action row.
- Increase field-to-action separation to `1.5rem`.
- Buttons must never touch a field underline, content block, or container edge.

```css
.form-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-block: 1.25rem;
}

.field + .form-actions {
  margin-top: 1.5rem;
}

.form-actions + .field,
.form-actions + section {
  margin-top: 1.5rem;
}
```

Use layout `gap` between buttons and container margins for surrounding
separation. Do not accumulate ad hoc margins on individual buttons.

## Implementation checklist

- Dotted bottom border only
- Accessible name independent of placeholder
- Named, programmatically connected validation message
- Conditional clear control with focus restoration
- Slim 1:3 pill proportions
- Circular icon-only controls
- Action-row gap and block separation
- Light and dark tokens
- Automated contrast and keyboard-focus verification

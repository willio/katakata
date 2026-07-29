# ADR 0006: Plain PHP Views

## Status

Accepted

## Context

Phase 2 (Rendering) needs to turn `Post`, `Author`, `Draft`, and
`Collection` objects into HTML. `resources/views/` exists but is
empty, and no templating approach has been decided.

The realistic options are: a template engine (Twig, Latte, Blade
standalone), or plain PHP files acting as views. ADR 0002 already
rules out pulling in a framework-scale dependency for something the
kernel can do itself, and per that ADR, Composer remains optional —
the application must run without `composer install`. A template
engine would require Composer at runtime, not just for dev tooling,
which breaks that guarantee. This narrows the decision considerably;
it isn't a close call between equally-valid options so much as ADR
0002 applied to one more subsystem.

## Decision

Views are plain `.php` files under `resources/views/`, rendered by a
small `View` class that:

- includes the view file inside an isolated scope (via a method that
  extracts an explicit array of variables, not `$this` or globals),
- captures output with `ob_start()`/`ob_get_clean()` and returns a
  string, so controllers/route closures keep returning `Response`
  objects rather than echoing directly,
- provides one helper, `e()`, for `htmlspecialchars($value,
  ENT_QUOTES, 'UTF-8')`, since plain PHP has no automatic output
  escaping and every interpolated value must be escaped by hand at
  the call site.

No layout/inheritance system, no component system, and no view
compilation/caching are introduced in this phase. A view including
another view is just `View::render('partials/header', $data)`
returning a string that gets concatenated or interpolated — nothing
more elaborate until a real need for it shows up.

## Consequences

- No dependency changes: the application still runs without
  `composer install`, consistent with ADR 0002.
- No automatic output escaping. Every value interpolated into a view
  must go through `e()` explicitly; forgetting this is an XSS risk
  the framework alternatives would have caught by default. This is
  the real cost of this decision and needs to be caught in code
  review, not assumed away.
- No template inheritance means shared layout (header/footer/nav) is
  done by explicit composition (rendering partials and concatenating
  strings), which is more verbose than a `{% extends %}`-style layout
  system. Acceptable at the current site scale; worth revisiting if
  the view count or nesting grows enough to make composition
  unwieldy.
- Views are ordinary PHP, so anything PHP can do (conditionals,
  loops, function calls) is available with no new syntax to learn —
  but that same power makes it easy to leak business logic into a
  view. Views should only format data the Renderer/controller already
  prepared, never query the Repository or make decisions themselves.
- Consistent with ADR 0003 (static-first): since views are cheap,
  dependency-free PHP includes, rendering stays easy to cache or
  precompute into static files later without restructuring how views
  are written.
- If real needs emerge later (layout inheritance, auto-escaping,
  component reuse across many views), that's a signal to revisit this
  ADR and adopt a real template engine deliberately — not to grow a
  bespoke one piecemeal, per ADR 0002's own guidance.

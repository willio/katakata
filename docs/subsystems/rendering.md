# Subsystem: Rendering

Phase: 2

## Purpose

The Rendering subsystem turns application-prepared data into generated
output. It does not read Markdown files or query the Content
Repository from inside views.

## Plain PHP Views

`Katakata\View` renders `.php` files under `resources/views/`. It:

- resolves a logical view name such as `home` or `article`,
- rejects unsafe view names and missing files,
- extracts only the explicit data array supplied by the caller,
- includes the file in a static closure so `$this` is unavailable,
- captures and returns output rather than sending it directly, and
- cleans the output buffer before rethrowing view exceptions.

Templates compose explicitly. If shared layout becomes necessary,
render the prepared content first and pass that HTML string to another
view; do not introduce an inheritance or layout subsystem without
revisiting ADR 0006.

## Markdown

`Katakata\Rendering\Markdown` converts a deliberately small prose
subset into HTML without a runtime dependency:

- headings and paragraphs
- blockquotes
- ordered and unordered lists
- fenced and inline code
- links
- emphasis and strong text

Raw HTML is escaped. Link destinations are limited to HTTP(S), mailto,
root-relative paths, and same-page fragments. The returned HTML is the
only deliberately unescaped value used by `article.php`.

This is a Katakata Markdown subset, not a complete CommonMark
implementation. Extend it only alongside fixtures and security tests;
if compatibility requirements grow substantially, revisit the
dependency decision instead of evolving an unbounded custom parser.

## Article Route

Published posts resolve at their canonical `Post::url()` path:

`/{year}/{month}/{slug}`

The route validates the date segments, loads content only through the
Repository, rejects drafts and canonical-path mismatches, renders the
Markdown body, and returns the plain PHP article view. Missing or
non-canonical posts return 404.

## Archive Route

`/archive` renders every published post in repository order, grouped
by year. `Katakata\\Rendering\\Archive` prepares that presentation
shape from the Repository's post collection; it excludes non-published
posts before the plain PHP view receives them.

The archive view links only to each post's canonical `Post::url()` and
escapes titles, dates, excerpts, URLs, year labels, and site metadata.

## Escaping

The global `e()` helper wraps:

```php
htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')
```

Every dynamic value interpolated into an HTML view must be passed
through `e()`. The sole exception is HTML returned by the Markdown
renderer after its own escaping and URL-policy pass.

## Deliberate Limits

- No layout inheritance
- No component system
- No view compilation or caching
- No business logic or Repository access in views
- No raw HTML in Markdown
- No feeds yet

These limits implement ADR 0006 without growing a bespoke template
language.

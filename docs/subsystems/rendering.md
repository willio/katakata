# Subsystem: Rendering

Phase: 2

## Purpose

The Rendering subsystem turns application-prepared data into generated
output. It does not read Markdown files or query the Content
Repository from inside views.

## Plain PHP Views

`Katakata\View` renders `.php` files under `resources/views/`. It:

- resolves a logical view name such as `home` to `home.php`,
- rejects unsafe view names and missing files,
- extracts only the explicit data array supplied by the caller,
- includes the file in a static closure so `$this` is unavailable,
- captures and returns output rather than sending it directly, and
- cleans the output buffer before rethrowing view exceptions.

Routes and future renderers remain responsible for preparing view
data and returning `Response` objects.

## Escaping

The global `e()` helper wraps:

```php
htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')
```

Every dynamic value interpolated into an HTML view must be passed
through `e()` at the call site. Plain PHP views do not auto-escape.

## Deliberate Limits

- No layout inheritance
- No component system
- No view compilation or caching
- No business logic or Repository access in views
- No Markdown-to-HTML conversion yet

These limits implement ADR 0006 without growing a bespoke template
language.

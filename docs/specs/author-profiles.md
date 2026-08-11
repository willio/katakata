# Author Profiles

Author profiles are canonical Markdown in the local `content/authors/` volume.
Posts reference a profile by its lowercase URL-safe slug in `author`; the
profile owns the public name, bio, avatar, and social URLs. Its public route is
`/authors/{slug}`.

```yaml
name: "Example Writer"
social: [https://example.com/examplewriter]
```

The flat `social` URL list complies with ADR 0005. Only HTTPS URLs render;
their host supplies the accessible label. The public page is an H1 with social
links at the far edge, bio beneath, then a ruled title/date index. Unresolved
legacy author names remain text, never guessed links.

## Slug selection

The default profile slug is the contributor's normalized first and last name
without a separator: `Example Writer` becomes `examplewriter`. During invite acceptance, a
user may provide a Katakata or Threads username; when it is available and
valid, that username becomes the canonical profile slug instead. Usernames are
lowercase URL-safe handles, must be unique, and are never silently changed
after publication. Reserved routes and existing profile slugs are rejected.

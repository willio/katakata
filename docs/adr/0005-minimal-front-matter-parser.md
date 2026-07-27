# ADR 0005: Minimal Front Matter Parser

## Status

Accepted

## Context

Front matter needs to express simple, flat metadata: a title, a date,
an author slug, a handful of tags, an excerpt, a status. It does not
need nested maps, multi-line strings, anchors, or any of the rest of
the YAML specification. A full YAML parser is either a large
hand-rolled undertaking or a third-party dependency — and per ADR
0002, Katakata avoids required runtime dependencies where a small,
readable implementation will do.

## Decision

`Katakata\Content\FrontMatter` implements a deliberately restricted
subset of YAML: scalar values (strings, integers, floats, booleans,
`null`), inline lists (`tags: [a, b]`), and simple block lists
(`tags:` followed by `- item` lines). It does not support nested
maps, multi-line block scalars, anchors/aliases, or YAML's full quoting
rules.

Any front matter line the parser can't interpret is ignored rather
than treated as an error — the file still parses, just without that
field, which then likely fails a required-field check further down
the Content Pipeline (see the Repository's validation step) with a
clear message about what's missing.

## Consequences

- Front matter files stay simple by construction — reaching for
  nested structure is a signal to reconsider the content model, not a
  capability to add to the parser.
- If real content genuinely needs deeper structure later (e.g. nested
  Threads sync metadata), that's a signal to either flatten the
  schema or revisit this ADR and adopt a real YAML library at that
  point — not to grow this parser piecemeal.
- Authors get slightly stricter syntax rules than full YAML (e.g. no
  multi-line strings), which should be documented for writers directly
  rather than discovered through parser quirks.
- This parser is intentionally not exposed as a general-purpose YAML
  tool elsewhere in the codebase; it exists only to serve front
  matter.

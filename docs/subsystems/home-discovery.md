# Home Discovery

The homepage is an editorial map, not a duplicate archive. Markdown post dates
remain the only input; rendering never uses request-time network calls,
analytics, or opaque popularity scores.

## Monthly shelves

The newest published post is the stable lead. All remaining published posts are
grouped by non-empty publication month, newest month first. Shelf density falls
predictably: 12 titles for the first month, 9 for the second, 6 for the third,
and 3 for every older month. Titles are never truncated and counts are never
rendered.

Each shelf links to its filtered archive at `/archive?year=YYYY&month=MM`.
When a shelf contains additional writing, it offers `Browse Month →` rather
than an inventory count.

## Weekly rotation

Visible titles are a stable page of a month shelf for one UTC-week-sized epoch.
The next week advances to the next page of that month before repeating. This
surfaces the whole archive over time without per-request randomness, unstable
reloads, or a false recommendation claim. The newest lead does not rotate.

## Article continuation

Every published article receives chronological `Newer` and `Earlier` links
when neighbors exist. This is navigation, not a recommendation system, and
uses only the published Markdown collection.

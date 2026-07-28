# Distribution Subsystem

Phase: 4 (in progress)

## Boundary

Distribution begins with a `Post` produced by the Repository. Adapters never read
Markdown files and never modify canonical content. `Distributor` invokes each
adapter independently and converts exceptions into failed `Delivery` results so
one downstream outage cannot undo publication or block another channel.

## Newsletter payload

`NewsletterAdapter` generates a provider-neutral JSON payload under
`storage/distribution/newsletter/YYYY/MM/{slug}.json`. The payload contains the
subject, canonical URL, publication time, safe HTML, and plain text derived from
the same Post used by the website and feeds.

The outbox is generated state and may be rebuilt. It is not canonical content.
No separate newsletter editor exists.

Run a channel explicitly:

```bash
php bin/katakata distribution:publish <post-slug> [newsletter]
```

## Deliberate limits

- Subscriber storage and consent rules are not yet specified.
- No email transport/provider is selected.
- Threads credentials and official API calls are not implemented in this slice.
- Automatic dispatch after publication waits for a transaction boundary that can
  record retry state without coupling publication success to delivery.

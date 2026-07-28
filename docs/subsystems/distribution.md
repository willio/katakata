# Distribution Subsystem

Phase: 5 (in progress)

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

## Subscriber consent

`SubscriberStore` keeps operational subscriber state in
`storage/distribution/subscribers.json`. This is not authored content and does
not weaken Markdown's canonical role.

The consent lifecycle is explicit:

1. A valid, normalized email requests subscription and enters `pending`.
2. A random confirmation capability expires after 48 hours and is stored only as
   a SHA-256 digest.
3. Confirmation is single-use and changes the subscriber to `active`.
4. Only active subscribers are eligible for delivery.
5. Unsubscribe changes eligibility immediately and records the transition.
6. A later subscription request starts a new confirmation cycle.

Unsubscribe capabilities are derived with HMAC-SHA-256 from
`NEWSLETTER_SECRET` (falling back to `APP_KEY`) and are not stored in
plaintext. The subscriber file is written atomically and restricted to mode
`0600` where the filesystem permits it.

## Configuration

Set a stable secret before constructing the subscriber store:

```env
NEWSLETTER_SECRET=a-long-random-secret
```

Changing this secret invalidates existing unsubscribe links.

## Public consent routes

- `GET /newsletter` renders the subscription form.
- `POST /newsletter/subscribe` creates a pending subscription without exposing
  whether an address already exists.
- `GET /newsletter/confirm?token=…` consumes a single-use confirmation link.
- `GET /newsletter/unsubscribe?token=…` renders the unsubscribe decision.
- `POST /newsletter/unsubscribe` performs immediate suppression behind CSRF
  validation.

Public responses never expose stored subscriber state or confirmation tokens.
Confirmation messages are enqueued immediately. Process due messages with:

```bash
php bin/katakata mail:work [limit]
```

The transport contract is provider-independent. The default filesystem transport
writes local delivery artifacts under `storage/distribution/mail/sent`; a
production provider implements the same `EmailTransport` interface.

## Durable delivery

Queue items live under `storage/distribution/mail/queue`. Their SHA-256
identifier is derived from a caller-supplied idempotency key. Re-enqueueing the
same logical message does not duplicate it. Each item records attempts, delivery
time, last error, and next attempt time. Temporary failures use bounded
exponential backoff; delivered items are never sent again.

Publication and consent changes do not wait for transport success. Operators may
run the worker from cron or a process supervisor.

## Deliberate limits

- The default transport is a local filesystem sink; no production email provider
  or provider credentials are selected yet.
- Threads credentials and official API calls are not implemented.
- Automatic dispatch after publication waits for a durable retry boundary so a
  downstream failure cannot affect canonical publication.

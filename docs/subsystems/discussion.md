# Discussion Subsystem

Phase: 5.7 (integration in progress)

## Boundary

Discussion is operational data separate from Markdown publication and
distribution. A provider may expose a normalized discussion reference, thread,
and entries, but publication must not create or synchronize a discussion.

## Native persistence

The native store keeps one moderated JSON thread per post in its configured
operational directory. These files are not canonical article content.

Every create, submit, moderation, and retention-prune mutation opens a
per-thread `{thread}.lock` file in mode `c`, acquires an exclusive lock, reads
the current thread only after that lock is held, then atomically replaces the
JSON file before releasing it. Concurrent submission, moderation, and pruning
therefore cannot discard another writer's update.

Native submissions are pending until moderation. The store rejects author names
over 120 Unicode characters, bodies over 5,000 Unicode characters, a filled
honeypot, and a second submission by the same author within five seconds. A
future public route must pass the honeypot through this store boundary and
render rejection without exposing moderation or spam details.

Provider registration, browser routes, and moderation UI remain separate
integration work and must depend on provider-neutral contracts rather than
directly on this filesystem store.

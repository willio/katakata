# Discussion Subsystem

Phase: 5.7 (native public submission integrated)

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
honeypot, and a second submission by the same author within five seconds. The
public article route passes its honeypot through this boundary and redirects
invalid submissions back to the article without revealing moderation or spam
details.

## Provider boundary and public route

`DiscussionManager` registers the null, Threads, and native providers during
application bootstrap. Dashboard buzz reads normalized entries through that
manager, so it no longer depends directly on Threads storage. Native comments
are rendered and submitted through `NativeDiscussionService`; the article route
never reaches into the filesystem store.

The public route only submits pending comments. Authenticated moderation UI and
moderator actions remain a separate integration step and must use an
owner/admin check plus a provider-neutral application service rather than
direct filesystem access.

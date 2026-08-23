# ADR 0012: Public platform extraction and downstream publications

## Status

Accepted for the Katakata public repository.

## Context

The reusable publishing platform and any publication built with it have different distribution, privacy, and editorial-content boundaries. The public repository must therefore contain the platform, documentation, tests, and synthetic fixtures, while private deployments retain their editorial content, operational state, credentials, and deployment-specific configuration.

The extraction preserves the original authorship, dates, merge topology, and development chronology where those records are safe to publish. It does not publish private editorial or operational paths. The original repository remains the exact-history archive for provenance; the public repository has a filtered history with new commit identifiers.

## Decision

The public platform is named Katakata and uses the `Katakata\` PHP namespace, the `katakata/katakata` Composer package, and the `bin/katakata` command. A downstream publication may depend on Katakata without contributing its private content or operational data to the public repository.

There is no compatibility alias for the former private deployment namespace before the first stable public release. Any future compatibility layer must be proposed as a separate, reviewed architectural change.

## Consequences

- Public fixtures remain synthetic and are safe for installation and tests.
- Editorial content, mailbox data, analytics, local overrides, generated artifacts, and credentials remain outside the public history.
- Filtered-history hashes cannot be compared directly with the exact-history archive, so the extraction manifest and private bundle are retained for provenance and audit.
- Downstream deployments should document their own content and operations boundaries rather than widening the public repository's scope.

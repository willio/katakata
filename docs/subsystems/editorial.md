# Editorial Subsystem

Phase 3 provides a filesystem-native editorial workflow.

## Boundaries

`DraftEditor` creates, updates, and schedules drafts. `RevisionStore` captures immutable pre-change Markdown under `content/revisions/`. `Scheduler` selects due drafts without side effects. `Publisher` moves a validated draft into the dated post convention through an atomic destination write.

The Repository remains the read boundary. Editorial services are the only write boundary.

## CLI workflow

```bash
php bin/katakata draft:create <slug> <title>
php bin/katakata draft:edit <slug>
php bin/katakata draft:schedule <slug> <ISO-8601>
php bin/katakata draft:publish <slug> [ISO-8601]
php bin/katakata publish:due
php bin/katakata revisions:list <slug>
```

`draft:edit` uses `$EDITOR`, captures the previous file as a revision, and installs the edited file atomically only when the editor exits successfully.

## Safety

Slugs are restricted to lowercase URL-safe words. Existing publication targets are never overwritten. Invalid or missing drafts fail before mutation. Every destructive draft transition first captures a revision.

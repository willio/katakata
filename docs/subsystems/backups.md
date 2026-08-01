# Operational Backups

`Katakata\Backup\BackupManager` creates gzip-compressed TAR archives from an explicit source map. It records a manifest containing every archived path, byte count, and SHA-256 digest, then writes a separate SHA-256 checksum for the finished archive. `verify()` checks both the archive checksum and every manifest entry before reporting an archive as valid.

## Privacy boundary

Backups are operational copies, never canonical content. The application backup source set includes Markdown content and sensitive operational data such as authentication accounts and passkeys, subscriber state, analytics, discussions, and mail-queue or delivery state. A backup can therefore contain personal data and credentials-derived material.

Backup directories are always owner-only (`0700`). Completed archives and their checksum sidecars are owner-readable only (`0600`), including when the directory already existed with a broader mode. Backups must remain outside every public document root and must never be added to Git, fixtures, or deployable assets.

## Integrity contract

Each archive includes `manifest.json`. The sidecar guards the compressed archive as a whole; the manifest guards each archived file after decompression. A failed checksum, missing manifest, missing entry, or mismatched entry digest makes verification fail. Backup creation removes incomplete artifacts when an error occurs.

The manager accepts only explicit filesystem paths. It ignores missing sources and skips files inside the backup destination, preventing recursive archive growth. The later CLI integration is responsible for selecting the standard application source map; it must not place archives under `public/`.

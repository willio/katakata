# Local Content Worktree

Katakata keeps Markdown as the canonical content source, but the `content/`
directory is intentionally excluded from Git. The application configuration in
`config/content.php` remains versioned; the content volume itself is local or
deployment-managed data.

The tracked `content/README.md` documents this boundary. Content backups and
recovery must be handled outside normal source-code commits. Never use a Git
cleanup command to remove ignored content from a working installation.

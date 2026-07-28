<?php
/** @var array<string, mixed> $user */
/** @var iterable<\Katakata\Content\Draft> $drafts */
/** @var \Katakata\Content\Draft|null $draft */
/** @var string $csrf */
/** @var bool $canInvite */
/** @var string|null $notice */
/** @var string $draftVersion */
$hasDraft = $draft !== null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $hasDraft ? e($draft->title) : 'New draft' ?> — Katakata</title>
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="editor-page">
<p class="editor-status" data-save-status aria-live="polite"><?= $hasDraft ? 'Saved' : 'Not saved' ?></p>
<button class="editor-settings-toggle" type="button" data-settings-toggle aria-label="Open settings" aria-controls="editor-panel" aria-expanded="false">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/>
        <path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21a2 2 0 1 1-4 0v-.09A1.7 1.7 0 0 0 8.5 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 4.6 8.5a1.7 1.7 0 0 0-.34-1.88l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3a2 2 0 1 1 4 0v.09A1.7 1.7 0 0 0 15.5 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9c.14.38.36.72.66 1 .3.28.69.43 1.1.4H21a2 2 0 1 1 0 4h-.09a1.7 1.7 0 0 0-1.51.6z"/>
    </svg>
</button>

<main class="editor-writing">
    <form
        id="draft-form"
        method="post"
        action="/editor/drafts"
        data-editor
        data-draft-id="<?= e($draft?->slug ?? '') ?>"
        data-autosave-url="<?= $hasDraft ? e('/editor/drafts/' . $draft->slug . '/autosave') : '' ?>"
        data-server-version="<?= e($draftVersion) ?>"
        data-server-updated-at="<?= e($draft?->updatedAt?->format(DATE_ATOM) ?? '') ?>"
    >
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <textarea name="body" aria-label="Markdown draft" placeholder="Begin writing…" autofocus><?= e($draft?->body ?? '') ?></textarea>

        <aside class="editor-panel" id="editor-panel" data-editor-panel hidden>
            <h1>Settings</h1>
            <?php if ($notice !== null): ?><p class="editor-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
            <div class="editor-fields">
                <label>Slug <input name="slug" value="<?= e($draft?->slug ?? '') ?>" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" required <?= $hasDraft ? 'readonly' : '' ?>></label>
                <label>Title <input name="title" value="<?= e($draft?->title ?? '') ?>" required></label>
            </div>
            <div class="editor-panel-actions">
                <button type="submit"><?= $hasDraft ? 'Save now' : 'Create draft' ?></button>
                <?php if ($hasDraft): ?><button type="submit" form="publish-form">Publish now</button><?php endif; ?>
                <button type="submit" form="logout-form">Sign out</button>
            </div>

            <h2>Drafts</h2>
            <p><a href="/editor/new">New draft</a></p>
            <ul>
                <?php foreach ($drafts as $item): ?>
                    <li><a href="/editor/drafts/<?= e($item->slug) ?>"><?= e($item->title) ?></a></li>
                <?php endforeach; ?>
            </ul>

            <h2>Account</h2>
            <p class="editor-notice"><?= e((string) $user['email']) ?></p>
            <div class="editor-panel-actions">
                <button type="button" data-passkey-register>Add a passkey</button>
                <span data-passkey-status aria-live="polite"></span>
            </div>

            <?php if ($canInvite): ?>
                <h2>Invite writer</h2>
                <div class="editor-fields">
                    <label>Email <input name="email" type="email" form="invite-form"></label>
                    <label>Role <select name="role" form="invite-form"><option value="editor">Editor</option><option value="admin">Admin</option></select></label>
                </div>
                <button type="submit" form="invite-form">Create invitation</button>
            <?php endif; ?>
        </aside>
    </form>

    <?php if ($hasDraft): ?>
        <form id="publish-form" method="post" action="/editor/drafts/<?= e($draft->slug) ?>/publish">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        </form>
    <?php endif; ?>
    <?php if ($canInvite): ?>
        <form id="invite-form" method="post" action="/editor/invitations">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        </form>
    <?php endif; ?>
    <form id="logout-form" method="post" action="/logout">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    </form>
</main>
<script src="/assets/js/editor.js" defer></script>
<script src="/assets/js/passkeys.js" defer></script>
</body>
</html>

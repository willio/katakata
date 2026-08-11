<?php
/** @var iterable<\Katakata\Content\Draft> $drafts */
/** @var \Katakata\Content\Draft|null $draft */
/** @var string $csrf */
/** @var string|null $notice */
/** @var string $draftVersion */
$hasDraft = $draft !== null;
$publishAsNewsletter = filter_var($draft?->meta['publish_as_newsletter'] ?? false, FILTER_VALIDATE_BOOL);
$discussionEnabled = filter_var($draft?->meta['discussion_enabled'] ?? false, FILTER_VALIDATE_BOOL);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $hasDraft ? e($draft->title) : 'New draft' ?> — Katakata</title>
    <link rel="stylesheet" href="/assets/css/site.css">
    <link rel="stylesheet" href="/assets/css/boundary.css">
</head>
<body class="editor-page">
<p class="editor-status" data-save-status role="status" aria-live="polite"></p>
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
        <textarea name="body" aria-label="Markdown draft" placeholder="Begin with your title…" autofocus><?= e($draft?->body ?? '') ?></textarea>

        <aside class="editor-panel" id="editor-panel" data-editor-panel hidden>
            <header class="editor-panel-header">
                <h1>Post settings</h1>
                <button class="editor-panel-close" type="button" data-settings-close aria-label="Close settings">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 6 6 18M6 6l12 12"/>
                    </svg>
                </button>
            </header>
            <?php if ($notice !== null): ?><p class="editor-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
            <div class="editor-fields">
                <label>Title <input name="title" value="<?= e($draft?->title ?? '') ?>" data-derived-title readonly></label>
                <label>Slug <input name="slug" value="<?= e($draft?->slug ?? '') ?>" data-derived-slug readonly></label>
                <p class="editor-notice">Title follows the first line. Slug follows the title.</p>
                <label><input type="checkbox" name="publish_as_newsletter" value="1"<?= $publishAsNewsletter ? ' checked' : '' ?>> Include this post in the newsletter</label>
                <label><input type="checkbox" name="discussion_enabled" value="1"<?= $discussionEnabled ? ' checked' : '' ?>> Enable discussion for this post</label>
                <p class="editor-notice"><a href="/dashboard/settings">Global publication settings</a></p>
            </div>
            <div class="editor-panel-actions">
                <button type="submit"><?= $hasDraft ? 'Save now' : 'Create draft' ?></button>
                <?php if ($hasDraft): ?><button type="submit" form="publish-form">Publish now</button><?php endif; ?>
            </div>

            <h2>Drafts</h2>
            <p><a href="/editor/new">New draft</a></p>
            <ul>
                <?php foreach ($drafts as $item): ?>
                    <li><a href="/editor/drafts/<?= e($item->slug) ?>"><?= e($item->title) ?></a></li>
                <?php endforeach; ?>
            </ul>

        </aside>
    </form>

    <?php if ($hasDraft): ?>
        <form id="publish-form" method="post" action="/editor/drafts/<?= e($draft->slug) ?>/publish">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        </form>
    <?php endif; ?>
</main>
<script src="/assets/js/editor-autosave.js" defer></script>
<script src="/assets/js/editor.js" defer></script>
</body>
</html>

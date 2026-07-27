<?php
/** @var array<string, mixed> $user */
/** @var iterable<\Katakata\Content\Draft> $drafts */
/** @var \Katakata\Content\Draft|null $draft */
/** @var string $csrf */
/** @var bool $canInvite */
/** @var string|null $notice */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editor — Katakata</title>
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body>
<header class="editor-bar">
    <a href="/editor">Katakata</a>
    <span><?= e((string) $user['email']) ?></span>
    <form method="post" action="/logout"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button>Sign out</button></form>
</header>
<main class="editor-shell">
    <aside>
        <h2>Drafts</h2>
        <a href="/editor/new">New draft</a>
        <ul>
            <?php foreach ($drafts as $item): ?>
                <li><a href="/editor/drafts/<?= e($item->slug) ?>"><?= e($item->title) ?></a></li>
            <?php endforeach; ?>
        </ul>
        <?php if ($canInvite): ?>
            <h2>Invite</h2>
            <form method="post" action="/editor/invitations">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <label>Email <input name="email" type="email" required></label>
                <label>Role <select name="role"><option value="editor">Editor</option><option value="admin">Admin</option></select></label>
                <button>Create invitation</button>
            </form>
        <?php endif; ?>
    </aside>
    <article>
        <?php if ($notice !== null): ?><p role="status"><?= e($notice) ?></p><?php endif; ?>
        <form method="post" action="/editor/drafts">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <label>Slug <input name="slug" value="<?= e($draft?->slug ?? '') ?>" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" required <?= $draft === null ? '' : 'readonly' ?>></label>
            <label>Title <input name="title" value="<?= e($draft?->title ?? '') ?>" required></label>
            <label>Markdown <textarea name="body" rows="24"><?= e($draft?->body ?? '') ?></textarea></label>
            <button>Save draft</button>
        </form>
        <?php if ($draft !== null): ?>
            <form method="post" action="/editor/drafts/<?= e($draft->slug) ?>/publish">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <button>Publish now</button>
            </form>
        <?php endif; ?>
    </article>
</main>
</body>
</html>

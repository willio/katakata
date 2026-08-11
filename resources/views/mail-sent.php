<?php
/** @var array<string, mixed> $user */
/** @var string $siteName */
/** @var list<\Katakata\Email\SentMessage> $messages */
/** @var string $csrf */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sent mail — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="/assets/css/site.css">
    <link rel="stylesheet" href="/assets/css/boundary.css">
</head>
<body class="dashboard-page mail-page">
<header class="dashboard-header">
    <a class="site-name" href="/dashboard"><?= e($siteName) ?></a>
    <nav aria-label="Mail actions">
        <a class="button" href="/mail/compose">Compose</a>
        <a href="/dashboard/settings">Settings</a>
    </nav>
</header>
<main class="dashboard-shell">
    <header class="dashboard-intro">
        <p class="eyebrow">Correspondence</p>
        <h1>Sent mail</h1>
        <p>Private delivery records created only after the outbound provider accepts a message.</p>
    </header>

    <p><a href="/mail?area=inbox">Back to Mail</a></p>

    <?php if ($messages === []): ?>
        <p class="quiet">No sent correspondence yet.</p>
    <?php else: ?>
        <ol class="mail-item-list">
            <?php foreach ($messages as $message): ?>
                <li>
                    <article>
                        <strong><?= e($message->subject !== '' ? $message->subject : 'Untitled message') ?></strong>
                        <span>To <?= e($message->to) ?></span>
                        <time datetime="<?= e($message->sentAt->format(DATE_ATOM)) ?>"><?= e($message->sentAt->format('M j, Y H:i')) ?></time>
                        <p><?= nl2br(e($message->text)) ?></p>
                    </article>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>

    <form class="form-actions" method="post" action="/logout">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <button type="submit">Sign out</button>
    </form>
</main>
</body>
</html>

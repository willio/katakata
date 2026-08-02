<?php
/** @var string $siteName */
/** @var \Katakata\Email\Message $message */
/** @var string $csrf */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($message->subject) ?> — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="/assets/css/site.css">
    <link rel="stylesheet" href="/assets/css/mail.css">
</head>
<body class="dashboard-page mail-page">
<header class="dashboard-header">
    <a class="site-name" href="/dashboard"><?= e($siteName) ?></a>
    <nav aria-label="Message actions">
        <a class="button" href="/mail/compose">Compose</a>
        <a href="/dashboard/settings">Settings</a>
    </nav>
</header>
<main class="dashboard-shell">
    <p><a href="/mail?area=inbox&amp;account=<?= rawurlencode($message->sourceAccountId ?? 'all') ?>">← <?= e($message->sourceLabel ?? 'Inbox') ?></a></p>
    <article class="mail-message">
        <header>
            <p class="eyebrow"><?= e($message->sourceLabel ?? 'Mailbox') ?> · <?= $message->unread ? 'Unread' : 'Read' ?></p>
            <h1><?= e($message->subject) ?></h1>
            <p class="quiet">From <?= e($message->from) ?> · received through <?= e($message->sourceLabel ?? 'the editorial inbox') ?> · <?= e($message->receivedAt->format('M j, Y H:i')) ?> UTC</p>
        </header>
        <div class="mail-message-body"><?= nl2br(e($message->text)) ?></div>

        <section class="mail-readiness" aria-labelledby="message-attachments">
            <h2 id="message-attachments">Attachments</h2>
            <p>Attachment files are not copied into Katakata. Open the original <?= e($message->sourceLabel ?? 'mailbox') ?> account in its mailbox application to review or download them.</p>
        </section>

        <div class="form-actions">
            <form method="post" action="/mail/messages/<?= rawurlencode($message->id) ?>/reply">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <button type="submit">Reply</button>
            </form>
            <form method="post" action="/mail/messages/<?= rawurlencode($message->id) ?>/<?= $message->unread ? 'read' : 'unread' ?>">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <button type="submit">Mark <?= $message->unread ? 'read' : 'unread' ?></button>
            </form>
            <form method="post" action="/mail/messages/<?= rawurlencode($message->id) ?>/archive">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <button type="submit">Archive</button>
            </form>
            <form method="post" action="/mail/messages/<?= rawurlencode($message->id) ?>/delete">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <button type="submit">Delete cached copy</button>
            </form>
        </div>
        <p class="quiet">Deleting removes only Katakata’s private cached copy. It does not change the original mailbox.</p>
    </article>
</main>
</body>
</html>

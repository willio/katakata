<?php
/** @var string $siteName */
/** @var \Katakata\Email\Draft $draft */
/** @var string $csrf */
/** @var ?string $error */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Compose — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="dashboard-page mail-page">
<header class="dashboard-header">
    <a class="site-name" href="/dashboard"><?= e($siteName) ?></a>
    <nav aria-label="Compose actions">
        <a href="/mail?area=inbox">Inbox</a>
        <a href="/dashboard/settings">Settings</a>
    </nav>
</header>
<main class="dashboard-shell">
    <header class="dashboard-intro">
        <p class="eyebrow">Correspondence</p>
        <h1>Compose</h1>
        <p>Drafts are stored privately and recovered from the Mail workspace.</p>
    </header>

    <?php if ($error !== null): ?><p role="alert"><?= e($error) ?></p><?php endif; ?>

    <form class="mail-compose-form" method="post" action="/mail/drafts/<?= rawurlencode($draft->id) ?>">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <label for="mail-to">To</label>
        <input id="mail-to" type="email" name="to" required value="<?= e($draft->to) ?>">
        <label for="mail-subject">Subject</label>
        <input id="mail-subject" name="subject" required value="<?= e($draft->subject) ?>">
        <label for="mail-text">Message</label>
        <textarea id="mail-text" name="text" rows="18" required><?= e($draft->text) ?></textarea>
        <div class="form-actions">
            <button type="submit" name="intent" value="save">Save draft</button>
            <button type="submit" name="intent" value="send">Send</button>
        </div>
    </form>
</main>
</body>
</html>

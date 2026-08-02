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
    <link rel="stylesheet" href="/assets/css/mail.css">
</head>
<body class="dashboard-page mail-page mail-compose-page">
<header class="dashboard-header">
    <a class="site-name" href="/dashboard"><?= e($siteName) ?></a>
    <nav aria-label="Compose actions">
        <a href="/mail?area=inbox">Mail</a>
        <a href="/dashboard/settings">Settings</a>
    </nav>
</header>
<main class="dashboard-shell mail-compose-shell">
    <header class="dashboard-intro">
        <p class="eyebrow">Correspondence</p>
        <h1>Compose mail</h1>
        <p class="quiet">Private draft. Saving does not send.</p>
    </header>

    <?php if ($error !== null): ?>
        <p class="mail-compose-alert" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form class="mail-compose-form" method="post" action="/mail/drafts/<?= rawurlencode($draft->id) ?>">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

        <div class="mail-compose-paper">
            <div class="mail-compose-field">
                <label for="mail-to">To</label>
                <input id="mail-to" type="email" name="to" required autocomplete="email" value="<?= e($draft->to) ?>">
            </div>
            <div class="mail-compose-field">
                <label for="mail-subject">Subject</label>
                <input id="mail-subject" name="subject" required value="<?= e($draft->subject) ?>">
            </div>
            <div class="mail-compose-body">
                <label for="mail-text">Message</label>
                <textarea id="mail-text" name="text" rows="22" required><?= e($draft->text) ?></textarea>
            </div>
        </div>

        <p class="quiet" role="status">Draft saved privately.</p>
        <div class="form-actions mail-compose-actions">
            <button type="submit" name="intent" value="save">Save draft</button>
            <button type="submit" name="intent" value="send">Send</button>
        </div>
    </form>
</main>
</body>
</html>

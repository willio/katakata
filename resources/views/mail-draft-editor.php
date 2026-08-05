<?php
/** @var \Katakata\Email\Draft $draft */
/** @var string $siteName */
/** @var string $csrf */
/** @var ?string $error */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($draft->subject !== '' ? $draft->subject : 'New message') ?> — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="/assets/css/site.css">
    <link rel="stylesheet" href="/assets/css/mail.css">
    <link rel="stylesheet" href="/assets/css/focused-editor.css">
</head>
<body class="editor-page mail-draft-editor focused-mail-editor">
<p class="editor-status" data-save-status role="status" aria-live="polite"></p>
<header class="focused-mail-editor-header">
    <a href="/mail?area=inbox">Back to Inbox</a>
    <span>Correspondence draft</span>
    <a href="/dashboard/settings">Settings</a>
</header>
<main class="editor-writing mail-draft-editor-frame focused-mail-editor-frame">
    <form
        method="post"
        action="/mail/drafts/<?= rawurlencode($draft->id) ?>"
        class="mail-compose-form"
        data-mail-draft-editor
        data-autosave-url="/mail/drafts/<?= rawurlencode($draft->id) ?>/autosave"
        data-server-version="<?= $draft->version ?>"
        data-server-updated-at="<?= e($draft->updatedAt->format(DATE_ATOM)) ?>"
    >
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="expected_version" value="<?= $draft->version ?>">
        <?php if ($error !== null && $error !== ''): ?><p class="mail-compose-error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <section class="mail-compose-paper" aria-label="Correspondence draft">
            <div class="mail-compose-field"><label for="mail-to">To</label><input id="mail-to" name="to" type="email" required value="<?= e($draft->to) ?>" autocomplete="email"></div>
            <div class="mail-compose-field"><label for="mail-subject">Subject</label><input id="mail-subject" name="subject" required value="<?= e($draft->subject) ?>"></div>
            <div class="mail-compose-body"><label for="mail-text">Message</label><textarea id="mail-text" name="text" required autofocus><?= e($draft->text) ?></textarea></div>
        </section>
        <div class="form-actions mail-compose-actions">
            <a href="/mail?area=inbox">Cancel</a>
            <button type="submit" name="intent" value="save">Save now</button>
            <button type="submit" name="intent" value="send">Send</button>
        </div>
    </form>
</main>
<script src="/assets/js/editor-autosave.js" defer></script>
<script src="/assets/js/mail-draft-editor.js" defer></script>
</body>
</html>

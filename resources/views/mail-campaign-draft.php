<?php
/** @var \Katakata\Mail\CampaignDraft $draft */
/** @var string $siteName */
/** @var string $csrf */
/** @var ?array{draft:\Katakata\Mail\CampaignDraft,recipient_count:int,recipients:list<array{email:string,unsubscribe_token:string}>,html:string,text:string,warnings:list<string>} $review */
/** @var string $error */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($draft->subject !== '' ? $draft->subject : 'Campaign draft') ?> — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="/assets/css/site.css">
    <link rel="stylesheet" href="/assets/css/boundary.css">
    <link rel="stylesheet" href="/assets/css/mail.css">
    <link rel="stylesheet" href="/assets/css/focused-editor.css">
</head>
<body class="editor-page mail-draft-editor-page campaign-draft-editor-page focused-mail-editor<?= ($buttonStyle ?? 'regular') === 'pill' ? ' buttons-pill' : '' ?>">
<p class="editor-status" id="campaign-save-state" data-save-status role="status" aria-live="polite">Saved version <?= $draft->version ?>.</p>
<header class="focused-mail-editor-header mail-draft-editor-header">
    <a href="/mail?area=campaigns">Back to campaigns</a>
    <span>Campaign draft</span>
    <a href="/dashboard/settings">Settings</a>
</header>
<main class="editor-writing mail-draft-editor-writing focused-mail-editor-frame">
    <?php if ($error === 'pending' || $error === 'queue'): ?>
        <section class="mail-readiness" role="alert" aria-labelledby="campaign-recovery-title">
            <h1 id="campaign-recovery-title">Campaign delivery is pending</h1>
            <p>The draft was claimed, but the campaign was not queued. Resume with the same campaign identity; successful recipients will not be duplicated.</p>
            <form method="post" action="/mail/campaign-drafts/<?= e($draft->id) ?>/resume"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button type="submit">Resume queueing</button></form>
        </section>
    <?php elseif ($error === 'not-pending'): ?>
        <p class="mail-compose-error" role="alert">This campaign draft has not been claimed for delivery.</p>
    <?php elseif ($error !== ''): ?>
        <p class="mail-compose-error" role="alert">Campaign delivery could not be completed. Review the draft and try again.</p>
    <?php endif; ?>

    <?php if (!$draft->isConfirmed()): ?>
        <form id="campaign-draft-form" class="mail-draft-editor-form" method="post" action="/mail/campaign-drafts/<?= e($draft->id) ?>" data-autosave-url="/mail/campaign-drafts/<?= e($draft->id) ?>/autosave" data-server-version="<?= $draft->version ?>" data-server-updated-at="<?= e($draft->updatedAt->format(DATE_ATOM)) ?>" data-campaign-draft>
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="expected_version" value="<?= $draft->version ?>">
            <input type="hidden" name="client_version" value="">
            <div class="mail-compose-paper campaign-compose-paper">
                <div class="mail-compose-field campaign-compose-field"><label for="campaign-audience">Audience</label><input id="campaign-audience" value="Calculated when reviewed" readonly></div>
                <div class="mail-compose-field campaign-compose-field"><label for="campaign-subject">Subject</label><input id="campaign-subject" name="subject" required value="<?= e($draft->subject) ?>" autofocus></div>
                <div class="mail-compose-field campaign-compose-field"><label for="campaign-preheader">Preheader</label><input id="campaign-preheader" name="preheader" value="<?= e($draft->preheader) ?>"></div>
                <div class="mail-compose-body campaign-compose-body"><label for="campaign-body">Body</label><textarea id="campaign-body" name="body" rows="22"><?= e($draft->body) ?></textarea></div>
            </div>
            <div class="mail-draft-editor-actions form-actions"><button type="submit" name="intent" value="save">Save draft</button><button type="submit" name="intent" value="review">Review campaign</button></div>
        </form>
    <?php else: ?>
        <section class="mail-readiness"><h1>Campaign draft claimed</h1><p class="quiet">Editing is locked after delivery confirmation begins.</p></section>
    <?php endif; ?>

    <?php if ($review !== null && !$draft->isConfirmed()): ?>
        <section class="mail-readiness campaign-review" aria-labelledby="campaign-review-title">
            <h1 id="campaign-review-title">Review campaign</h1>
            <p><strong><?= $review['recipient_count'] ?></strong> currently eligible confirmed <?= $review['recipient_count'] === 1 ? 'subscriber' : 'subscribers' ?>.</p>
            <p class="quiet">Audience is calculated now and snapshotted only when delivery is confirmed and queued.</p>
            <?php if ($review['warnings'] !== []): ?><ul><?php foreach ($review['warnings'] as $warning): ?><li><?= e($warning) ?></li><?php endforeach; ?></ul><?php endif; ?>
            <?php if ($review['recipient_count'] > 0 && $draft->subject !== '' && trim($draft->body) !== ''): ?>
                <form method="post" action="/mail/campaign-drafts/<?= e($draft->id) ?>/confirm"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="expected_version" value="<?= $draft->version ?>"><button type="submit">Confirm and queue</button></form>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
<script src="/assets/js/editor-autosave.js" defer></script>
<script src="/assets/js/campaign-draft.js" defer></script>
</body>
</html>

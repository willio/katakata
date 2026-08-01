<?php
/** @var \Katakata\Mail\CampaignDraft $draft */
/** @var string $siteName */
/** @var string $csrf */
/** @var ?array{draft:\Katakata\Mail\CampaignDraft,recipient_count:int,recipients:list<array{email:string,unsubscribe_token:string}>,html:string,text:string,warnings:list<string>} $review */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Campaign draft — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="dashboard-page mail-page campaign-draft-page">
<header class="dashboard-header">
    <a class="site-name" href="/dashboard"><?= e($siteName) ?></a>
    <nav aria-label="Campaign draft actions">
        <button type="button" id="campaign-fullscreen-toggle" aria-pressed="false">Fullscreen</button>
        <a href="/mail?area=campaigns">Mail</a>
        <a href="/dashboard/settings">Settings</a>
    </nav>
</header>
<main class="dashboard-shell campaign-compose-shell">
    <header class="dashboard-intro">
        <p class="eyebrow">Newsletter</p>
        <h1>Campaign draft</h1>
        <p class="quiet">Separate from the source post. Saving or reviewing this draft never sends mail.</p>
    </header>

    <form id="campaign-draft-form" method="post" action="/mail/campaign-drafts/<?= e($draft->id) ?>" data-autosave-url="/mail/campaign-drafts/<?= e($draft->id) ?>/autosave">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="expected_version" value="<?= $draft->version ?>">
        <input type="hidden" name="client_version" value="">

        <div class="campaign-compose-paper">
            <div class="campaign-compose-field">
                <label for="campaign-audience">Audience</label>
                <input id="campaign-audience" value="All confirmed subscribers" readonly>
            </div>
            <div class="campaign-compose-field">
                <label for="campaign-subject">Subject</label>
                <input id="campaign-subject" name="subject" required value="<?= e($draft->subject) ?>">
            </div>
            <div class="campaign-compose-field">
                <label for="campaign-preheader">Preheader</label>
                <input id="campaign-preheader" name="preheader" value="<?= e($draft->preheader) ?>">
            </div>
            <div class="campaign-compose-body">
                <label for="campaign-body">Body</label>
                <textarea id="campaign-body" name="body" rows="22"><?= e($draft->body) ?></textarea>
            </div>
        </div>

        <p id="campaign-save-state" class="quiet" role="status">Saved version <?= $draft->version ?>.</p>
        <div class="form-actions">
            <button type="submit" name="intent" value="save">Save draft</button>
            <button type="submit" name="intent" value="review">Review campaign</button>
        </div>
    </form>

    <?php if ($review !== null): ?>
        <section class="mail-readiness" aria-labelledby="campaign-review-title">
            <h2 id="campaign-review-title">Review campaign</h2>
            <p><strong><?= $review['recipient_count'] ?></strong> currently eligible confirmed <?= $review['recipient_count'] === 1 ? 'subscriber' : 'subscribers' ?>.</p>
            <p class="quiet">Audience is calculated now and will be snapshotted only when delivery is confirmed and queued.</p>
            <?php if ($review['warnings'] !== []): ?>
                <ul>
                    <?php foreach ($review['warnings'] as $warning): ?><li><?= e($warning) ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($review['recipient_count'] > 0 && $draft->subject !== '' && trim($draft->body) !== ''): ?>
                <form method="post" action="/mail/campaign-drafts/<?= e($draft->id) ?>/confirm">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="expected_version" value="<?= $draft->version ?>">
                    <button type="submit">Confirm and queue</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
<script src="/assets/js/campaign-draft.js" defer></script>
</body>
</html>

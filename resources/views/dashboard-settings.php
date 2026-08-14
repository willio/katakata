<?php
/** @var array<string, mixed> $user */
/** @var string $siteName */
/** @var array<string, array<string, scalar|null>> $settings */
/** @var array<string, array<string, mixed>> $readiness */
/** @var bool $saved */
/** @var ?string $error */
/** @var string $csrf */

$publication = $settings['publication'] ?? [];
$newsletter = $settings['newsletter'] ?? [];
$discussion = $settings['discussion'] ?? [];
$analytics = $settings['analytics'] ?? [];
$mailbox = $readiness['mailbox'] ?? [];
$feedbackRole = $error === null ? 'status' : 'alert';
$feedback = $error ?? ($saved ? 'Settings saved.' : null);
$mailboxStatus = strtolower(trim((string) ($mailbox['status'] ?? 'needs_setup')));
$mailboxProductStatus = match ($mailboxStatus) {
    'ready' => 'Available',
    'partial', 'error' => 'Needs attention',
    'disabled' => 'Paused',
    default => 'Waiting for setup',
};
$mailboxProductDetail = match ($mailboxProductStatus) {
    'Available' => 'Reader correspondence is available in Mail.',
    'Needs attention' => 'Some reader correspondence needs attention before it can stay fully available.',
    'Paused' => 'Reader correspondence is paused.',
    default => 'Add a mailbox account when you are ready to receive reader correspondence.',
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Settings — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="/assets/css/site.css">
    <link rel="stylesheet" href="/assets/css/boundary.css">
</head>
<body class="dashboard-page dashboard-settings-page">
<header class="dashboard-header">
    <a class="site-name" href="/dashboard"><?= e($siteName) ?></a>
    <nav aria-label="Settings actions"><a class="button" href="/editor/new">New post</a><a aria-current="page" href="/dashboard/settings">Settings</a></nav>
</header>
<main class="dashboard-shell">
    <header class="dashboard-intro">
        <p class="eyebrow">Publication</p>
        <h1>Settings</h1>
        <p>Publication defaults, reader communication, and editorial preferences.</p>
    </header>

    <div class="settings-layout">
        <nav class="settings-folio" aria-label="Settings sections">
            <a href="#publication">Publication</a>
            <a href="#newsletter">Newsletter</a>
            <a href="#mailbox">Reader inbox</a>
            <a href="#discussion">Discussion</a>
            <a href="#analytics">Analytics</a>
        </nav>

        <div class="settings-sections">
            <section id="publication" class="settings-section">
                <header><h2>Publication</h2><p class="quiet">Reader-facing identity and default authorship.</p></header>
                <?php if ($feedback !== null): ?><p class="settings-feedback" role="<?= e($feedbackRole) ?>"><?= e($feedback) ?></p><?php endif; ?>
                <form method="post" action="/dashboard/settings">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="section" value="publication">
                    <label for="publication-name">Publication name</label><input id="publication-name" name="name" required value="<?= e((string) ($publication['name'] ?? '')) ?>">
                    <label for="publication-description">Description</label><textarea id="publication-description" name="description"><?= e((string) ($publication['description'] ?? '')) ?></textarea>
                    <label for="publication-author">Default author</label><input id="publication-author" name="default_author" value="<?= e((string) ($publication['default_author'] ?? '')) ?>">
                    <button type="submit">Save publication</button>
                </form>
            </section>

            <section id="newsletter" class="settings-section">
                <header><h2>Newsletter</h2><p class="quiet">Defaults for publication-to-email delivery.</p></header>
                <p class="readiness"><strong><?= e($readiness['newsletter']['status']) ?></strong> — <?= e($readiness['newsletter']['detail']) ?></p>
                <form method="post" action="/dashboard/settings">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="section" value="newsletter">
                    <label for="newsletter-sender">Sender label</label><input id="newsletter-sender" name="sender_label" value="<?= e((string) ($newsletter['sender_label'] ?? '')) ?>">
                    <label class="checkbox-row"><input type="checkbox" name="publish_by_default" value="1"<?= !empty($newsletter['publish_by_default']) ? ' checked' : '' ?>> Include new posts by default</label>
                    <button type="submit">Save newsletter</button>
                </form>
            </section>

            <section id="mailbox" class="settings-section settings-readonly">
                <header><h2>Reader inbox</h2><p class="quiet">Correspondence from readers appears in Mail when an inbox is connected.</p></header>
                <p class="readiness"><strong><?= e($mailboxProductStatus) ?></strong> — <?= e($mailboxProductDetail) ?></p>
                <div class="form-actions"><a class="button" href="/dashboard/settings/mailboxes">Manage reader inboxes</a></div>
            </section>

            <section id="discussion" class="settings-section">
                <header><h2>Discussion</h2><p class="quiet">Choose how discussion is offered on new posts.</p></header>
                <p class="readiness"><strong><?= e($readiness['discussion']['status']) ?></strong> — <?= e($readiness['discussion']['detail']) ?></p>
                <form method="post" action="/dashboard/settings">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="section" value="discussion">
                    <label for="discussion-provider">Provider</label>
                    <select id="discussion-provider" name="provider"><?php foreach (['none' => 'Disabled', 'native' => 'Native', 'threads' => 'Threads'] as $value => $label): ?><option value="<?= e($value) ?>"<?= ($discussion['provider'] ?? 'none') === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
                    <label class="checkbox-row"><input type="checkbox" name="enabled_by_default" value="1"<?= !empty($discussion['enabled_by_default']) ? ' checked' : '' ?>> Enable discussion on new posts</label>
                    <button type="submit">Save discussion</button>
                </form>
            </section>

            <section id="analytics" class="settings-section">
                <header><h2>Analytics</h2><p class="quiet">Display preferences for privacy-bounded readership data.</p></header>
                <p class="readiness"><strong><?= e($readiness['analytics']['status']) ?></strong> — <?= e($readiness['analytics']['detail']) ?></p>
                <form method="post" action="/dashboard/settings">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="section" value="analytics">
                    <label for="analytics-period">Dashboard period</label>
                    <select id="analytics-period" name="dashboard_period"><?php foreach (['7d' => '7 days', '30d' => '30 days', '90d' => '90 days'] as $value => $label): ?><option value="<?= e($value) ?>"<?= ($analytics['dashboard_period'] ?? '30d') === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
                    <button type="submit">Save analytics</button>
                </form>
            </section>
        </div>
    </div>
</main>
</body>
</html>

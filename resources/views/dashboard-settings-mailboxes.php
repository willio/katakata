<?php
/** @var string $siteName */
/** @var list<array{account:\Katakata\Email\MailboxAccount,missing:list<string>,readiness:array<string,mixed>}> $accounts */
/** @var bool $saved */
/** @var ?string $error */
/** @var string $csrf */
/** @var int $limit */

$statusLabel = static function (\Katakata\Email\MailboxAccount $account, array $readiness): string {
    if (!$account->enabled) return 'Paused';
    return match (strtolower((string) ($readiness['status'] ?? 'needs_setup'))) {
        'ready' => 'Available',
        'error', 'partial' => 'Needs attention',
        default => 'Waiting for setup',
    };
};
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Reader inboxes — <?= e($siteName) ?></title><link rel="stylesheet" href="/assets/css/site.css"><link rel="stylesheet" href="/assets/css/boundary.css"></head>
<body class="dashboard-page dashboard-settings-page<?= ($buttonStyle ?? 'regular') === 'pill' ? ' buttons-pill' : '' ?>">
<header class="dashboard-header"><a class="site-name" href="/dashboard"><?= e($siteName) ?></a><nav><a href="/dashboard/settings">Settings</a><a href="/mail">Mail</a></nav></header>
<main class="dashboard-shell settings-mailboxes-shell">
<header class="dashboard-intro"><p class="eyebrow">Settings</p><h1>Reader inboxes</h1><p>Connect and name the inboxes used for reader correspondence.</p><?php if (count($accounts) < $limit): ?><div class="form-actions"><a class="button" href="#add-mailbox">Add inbox</a><a href="/dashboard/settings/mailboxes/import">Import profile</a></div><?php endif; ?></header>
<?php if ($saved): ?><p class="settings-feedback" role="status">Reader inbox settings saved.</p><?php endif; ?>
<?php if ($error !== null && $error !== ''): ?><p class="settings-feedback" role="alert"><?= e($error) ?></p><?php endif; ?>
<section class="settings-section"><header><h2>Connected inboxes</h2><p class="quiet"><?= count($accounts) ?> of <?= $limit ?> inboxes connected.</p></header>
<?php if ($accounts === []): ?><p class="quiet">No reader inboxes are connected yet.</p><?php endif; ?>
<?php foreach ($accounts as $entry): ?><?php $account = $entry['account']; $readiness = $entry['readiness']; $state = $statusLabel($account, $readiness); ?>
<article class="settings-section settings-mailbox-card">
<header><h3><?= e($account->label) ?></h3><p class="readiness"><strong><?= e($state) ?></strong> — <?= e(match ($state) { 'Available' => 'Reader correspondence is available in Mail.', 'Needs attention' => 'This inbox needs attention before it can stay available.', 'Paused' => 'This inbox is not included in Mail.', default => 'Private connection setup is still required before this inbox becomes available.' }) ?></p></header>
<form method="post" action="/dashboard/settings/mailboxes/<?= rawurlencode($account->id) ?>"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><label>Inbox name</label><input name="label" required value="<?= e($account->label) ?>"><button type="submit">Save inbox name</button></form>
<div class="form-actions"><form method="post" action="/dashboard/settings/mailboxes/<?= rawurlencode($account->id) ?>/<?= $account->enabled ? 'disable' : 'enable' ?>"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button type="submit"><?= $account->enabled ? 'Pause inbox' : 'Resume inbox' ?></button></form></div>
<details><summary>Remove inbox</summary><form method="post" action="/dashboard/settings/mailboxes/<?= rawurlencode($account->id) ?>/delete"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><p class="quiet">Type <strong><?= e($account->id) ?></strong> to confirm removal.</p><label for="confirm-remove-<?= e($account->id) ?>">Inbox ID</label><input id="confirm-remove-<?= e($account->id) ?>" name="confirm" required autocomplete="off" pattern="<?= e($account->id) ?>"><label class="checkbox-row"><input type="checkbox" name="purge_cache" value="1"> Remove private cached copies from this publication</label><button type="submit">Remove inbox</button></form><p class="quiet">Messages in the original email account are not changed.</p></details>
</article>
<?php endforeach; ?></section>
<?php if (count($accounts) < $limit): ?>
<section class="settings-section" id="add-mailbox"><header><h2>Add reader inbox</h2><p class="quiet">Import a supported email profile, or ask the publication operator to complete the private connection setup.</p></header><div class="form-actions"><a class="button" href="/dashboard/settings/mailboxes/import">Import profile</a></div><p class="quiet">Server addresses and credentials stay outside the normal owner workspace.</p></section>
<?php endif; ?>
</main></body></html>

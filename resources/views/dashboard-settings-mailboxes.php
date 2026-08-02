<?php
/** @var string $siteName */
/** @var list<array{account:\Katakata\Email\MailboxAccount,missing:list<string>,readiness:array<string,mixed>}> $accounts */
/** @var bool $saved */
/** @var ?string $error */
/** @var string $csrf */
/** @var int $limit */
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mailbox accounts — <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="dashboard-page dashboard-settings-page">
<header class="dashboard-header">
<a class="site-name" href="/dashboard"><?= e($siteName) ?></a>
<nav><a href="/dashboard/settings">Settings</a><a href="/mail">Mail</a></nav>
</header>
<main class="dashboard-shell">
<header class="dashboard-intro">
<p class="eyebrow">Settings · Mailbox</p>
<h1>Mailbox accounts</h1>
<p>Manage up to <?= $limit ?> cached IMAP sources. Connection credentials remain deployment-managed.</p>
<div class="form-actions"><a class="button" href="/dashboard/settings/mailboxes/import">Import configuration profile</a></div>
</header>
<?php if ($saved): ?><p class="settings-feedback" role="status">Mailbox settings saved.</p><?php endif; ?>
<?php if ($error !== null && $error !== ''): ?><p class="settings-feedback" role="alert"><?= e($error) ?></p><?php endif; ?>
<section class="settings-section">
<header><h2>Configured accounts</h2><p class="quiet"><?= count($accounts) ?> of <?= $limit ?> configured.</p></header>
<?php if ($accounts === []): ?><p class="quiet">No mailbox accounts configured.</p><?php endif; ?>
<?php foreach ($accounts as $entry): ?>
<?php $account = $entry['account']; $readiness = $entry['readiness']; ?>
<article class="settings-section" id="mailbox-<?= e($account->id) ?>">
<header>
<p class="eyebrow"><?= e($account->id) ?></p>
<h3><?= e($account->label) ?></h3>
<p class="readiness"><strong><?= $account->enabled ? e(ucfirst((string) ($readiness['status'] ?? 'needs setup'))) : 'Disabled' ?></strong> — <?= e((string) ($account->enabled ? ($readiness['reason'] ?? 'Cache readiness unavailable.') : 'Excluded from synchronization and Inbox aggregation.')) ?></p>
</header>
<form method="post" action="/dashboard/settings/mailboxes/<?= rawurlencode($account->id) ?>">
<input type="hidden" name="csrf" value="<?= e($csrf) ?>">
<label>Account ID</label><input value="<?= e($account->id) ?>" disabled>
<label>Label</label><input name="label" required value="<?= e($account->label) ?>">
<label>IMAP host</label><input name="host" required value="<?= e($account->host) ?>">
<label>Port</label><input type="number" name="port" min="1" max="65535" required value="<?= $account->port ?>">
<label>Mailbox</label><input name="mailbox" required value="<?= e($account->mailbox) ?>">
<label>Username variable name</label><input name="username_secret" required value="<?= e($account->usernameSecret) ?>">
<label>Password variable name</label><input name="password_secret" required value="<?= e($account->passwordSecret) ?>">
<input type="hidden" name="enabled" value="<?= $account->enabled ? '1' : '0' ?>">
<button type="submit">Save account</button>
</form>
<?php if ($entry['missing'] !== []): ?><p class="quiet">Missing deployment variables: <code><?= e(implode(', ', $entry['missing'])) ?></code></p><?php endif; ?>
<p class="quiet">Direct TLS only · Last synchronized: <?= e((string) ($readiness['last_synced_at'] ?? 'Never')) ?></p>
<div class="form-actions">
<form method="post" action="/dashboard/settings/mailboxes/<?= rawurlencode($account->id) ?>/<?= $account->enabled ? 'disable' : 'enable' ?>">
<input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button type="submit"><?= $account->enabled ? 'Disable' : 'Enable' ?></button>
</form>
</div>
<details>
<summary>Remove account</summary>
<form method="post" action="/dashboard/settings/mailboxes/<?= rawurlencode($account->id) ?>/delete">
<input type="hidden" name="csrf" value="<?= e($csrf) ?>">
<label>Type <code><?= e($account->id) ?></code> to confirm</label><input name="confirm" required>
<label class="checkbox-row"><input type="checkbox" name="purge_cache" value="1"> Also delete this account’s private cached copies</label>
<button type="submit">Remove account</button>
</form>
<p class="quiet">The remote mailbox is never changed.</p>
</details>
</article>
<?php endforeach; ?>
</section>
<?php if (count($accounts) < $limit): ?>
<section class="settings-section" id="add-mailbox">
<header><h2>Add mailbox account</h2><p class="quiet">Save non-secret connection metadata and deployment variable names only, or import a standard Apple Mail configuration profile.</p></header>
<div class="form-actions"><a href="/dashboard/settings/mailboxes/import">Import configuration profile</a></div>
<form method="post" action="/dashboard/settings/mailboxes">
<input type="hidden" name="csrf" value="<?= e($csrf) ?>">
<label>Account ID</label><input name="id" pattern="[a-z0-9][a-z0-9_-]{1,31}" required placeholder="letters">
<label>Label</label><input name="label" required placeholder="Letters">
<label>IMAP host</label><input name="host" required placeholder="imap.example.com">
<label>Port</label><input type="number" name="port" min="1" max="65535" required value="993">
<label>Mailbox</label><input name="mailbox" required value="INBOX">
<label>Username variable name</label><input name="username_secret" required placeholder="IMAP_LETTERS_USERNAME">
<label>Password variable name</label><input name="password_secret" required placeholder="IMAP_LETTERS_PASSWORD">
<label class="checkbox-row"><input type="checkbox" name="enabled" value="1" checked> Enable this account</label>
<button type="submit">Add mailbox account</button>
</form>
</section>
<?php endif; ?>
<section class="settings-section settings-readonly">
<header><h2>Synchronization</h2></header>
<p>Run <code>php private/jobs/sync-mail.php</code>, or add <code>--account=&lt;id&gt;</code> for one account.</p>
<p class="quiet">Settings never opens an IMAP connection during a web request.</p>
</section>
</main>
</body>
</html>

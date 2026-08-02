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
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Settings — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="dashboard-page dashboard-settings-page">
<header class="dashboard-header">
    <a class="site-name" href="/dashboard"><?= e($siteName) ?></a>
    <nav aria-label="Settings actions">
        <a class="button" href="/editor/new">New post</a>
        <a aria-current="page" href="/dashboard/settings">Settings</a>
    </nav>
</header>
<main class="dashboard-shell">
    <header class="dashboard-intro">
        <p class="eyebrow">Global configuration</p>
        <h1>Settings</h1>
        <p>Publication-wide defaults and integration readiness. Post-specific controls remain in the editor.</p>
    </header>

    <div class="settings-layout">
        <nav class="settings-folio" aria-label="Settings sections">
            <a href="#publication">Publication</a>
            <a href="#newsletter">Newsletter</a>
            <a href="#mailbox">Mailbox</a>
            <a href="#discussion">Discussion</a>
            <a href="#analytics">Analytics</a>
            <a href="#appearance">Appearance</a>
            <a href="#account">Account &amp; Security</a>
            <a href="#system">System</a>
        </nav>

        <div class="settings-sections">
            <section id="publication" class="settings-section">
                <header><h2>Publication</h2><p class="quiet">Reader-facing identity and default authorship.</p></header>
                <?php if ($feedback !== null): ?><p class="settings-feedback" role="<?= e($feedbackRole) ?>"><?= e($feedback) ?></p><?php endif; ?>
                <form method="post" action="/dashboard/settings">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="section" value="publication">
                    <label for="publication-name">Publication name</label>
                    <input id="publication-name" name="name" required value="<?= e((string) ($publication['name'] ?? '')) ?>">
                    <label for="publication-description">Description</label>
                    <textarea id="publication-description" name="description"><?= e((string) ($publication['description'] ?? '')) ?></textarea>
                    <label for="publication-author">Default author</label>
                    <input id="publication-author" name="default_author" value="<?= e((string) ($publication['default_author'] ?? '')) ?>">
                    <button type="submit">Save publication</button>
                </form>
            </section>

            <section id="newsletter" class="settings-section">
                <header><h2>Newsletter</h2><p class="quiet">Defaults for publication-to-email delivery.</p></header>
                <p class="readiness"><strong><?= e($readiness['newsletter']['status']) ?></strong> — <?= e($readiness['newsletter']['detail']) ?></p>
                <form method="post" action="/dashboard/settings">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="section" value="newsletter">
                    <label for="newsletter-sender">Sender label</label>
                    <input id="newsletter-sender" name="sender_label" value="<?= e((string) ($newsletter['sender_label'] ?? '')) ?>">
                    <label class="checkbox-row"><input type="checkbox" name="publish_by_default" value="1"<?= !empty($newsletter['publish_by_default']) ? ' checked' : '' ?>> Include new posts by default</label>
                    <button type="submit">Save newsletter</button>
                </form>
            </section>

            <section id="mailbox" class="settings-section settings-readonly">
                <header>
                    <h2>Mailbox</h2>
                    <p class="quiet">Reader correspondence is synchronized into private operational storage. Credentials remain in the environment or host secret manager.</p>
                </header>
                <p class="readiness"><strong><?= e((string) ($mailbox['status'] ?? 'Needs setup')) ?></strong> — <?= e((string) ($mailbox['detail'] ?? 'Mailbox readiness is unavailable.')) ?></p>

                <dl class="settings-status-list">
                    <div><dt>Host</dt><dd><?= e((string) (($mailbox['host'] ?? '') !== '' ? $mailbox['host'] : 'Not configured')) ?></dd></div>
                    <div><dt>Port</dt><dd><?= e((string) ($mailbox['port'] ?? 993)) ?></dd></div>
                    <div><dt>Encryption</dt><dd><?= e(strtoupper((string) ($mailbox['encryption'] ?? 'ssl'))) ?></dd></div>
                    <div><dt>Mailbox</dt><dd><?= e((string) ($mailbox['mailbox'] ?? 'INBOX')) ?></dd></div>
                    <div><dt>Last synchronized</dt><dd><?= e((string) ($mailbox['last_synced_at'] ?? 'Never')) ?></dd></div>
                </dl>

                <?php if (($mailbox['missing'] ?? []) !== []): ?>
                    <div class="mail-readiness" role="status">
                        <h3>Deployment variables required</h3>
                        <p class="quiet">Set these outside the application UI:</p>
                        <code><?= e(implode(', ', array_map('strval', (array) $mailbox['missing']))) ?></code>
                    </div>
                <?php endif; ?>

                <div class="mail-readiness">
                    <h3>Scheduled synchronization</h3>
                    <p>Run <code>php private/jobs/sync-mail.php</code> from the project root, then schedule it with cron or the host scheduler.</p>
                    <p class="quiet">The web request path reads only from <code>storage/mail/cache</code>; it never connects to IMAP directly.</p>
                </div>
            </section>

            <section id="discussion" class="settings-section">
                <header><h2>Discussion</h2><p class="quiet">Choose the default discussion provider without exposing credentials.</p></header>
                <p class="readiness"><strong><?= e($readiness['discussion']['status']) ?></strong> — <?= e($readiness['discussion']['detail']) ?></p>
                <form method="post" action="/dashboard/settings">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="section" value="discussion">
                    <label for="discussion-provider">Provider</label>
                    <select id="discussion-provider" name="provider">
                        <?php foreach (['none' => 'Disabled', 'native' => 'Native', 'threads' => 'Threads'] as $value => $label): ?>
                            <option value="<?= e($value) ?>"<?= ($discussion['provider'] ?? 'none') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="checkbox-row"><input type="checkbox" name="enabled_by_default" value="1"<?= !empty($discussion['enabled_by_default']) ? ' checked' : '' ?>> Enable discussion on new posts</label>
                    <button type="submit">Save discussion</button>
                </form>
            </section>

            <section id="analytics" class="settings-section">
                <header><h2>Analytics</h2><p class="quiet">Display preferences for privacy-bounded readership data.</p></header>
                <p class="readiness"><strong><?= e($readiness['analytics']['status']) ?></strong> — <?= e($readiness['analytics']['detail']) ?></p>
                <form method="post" action="/dashboard/settings">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="section" value="analytics">
                    <label for="analytics-period">Dashboard period</label>
                    <select id="analytics-period" name="dashboard_period">
                        <?php foreach (['7d' => '7 days', '30d' => '30 days', '90d' => '90 days'] as $value => $label): ?>
                            <option value="<?= e($value) ?>"<?= ($analytics['dashboard_period'] ?? '30d') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Save analytics</button>
                </form>
            </section>

            <section id="appearance" class="settings-section settings-readonly">
                <header><h2>Appearance</h2><p class="quiet"><strong><?= e($readiness['appearance']['status']) ?></strong> — <?= e($readiness['appearance']['detail']) ?></p></header>
            </section>

            <section id="account" class="settings-section settings-readonly">
                <header><h2>Account &amp; Security</h2><p class="quiet"><strong><?= e($readiness['account']['status']) ?></strong> — <?= e($readiness['account']['detail']) ?></p></header>
            </section>

            <section id="system" class="settings-section settings-readonly">
                <header><h2>System</h2><p class="quiet"><strong><?= e($readiness['system']['status']) ?></strong> — <?= e($readiness['system']['detail']) ?></p></header>
            </section>
        </div>
    </div>
</main>
</body>
</html>

<?php
/** @var array<string, mixed> $user */
/** @var string $siteName */
/** @var array<string, array<string, scalar|null>> $settings */
/** @var bool $saved */
/** @var ?string $error */
/** @var string $csrf */

$publication = $settings['publication'] ?? [];
$newsletter = $settings['newsletter'] ?? [];
$discussion = $settings['discussion'] ?? [];
$analytics = $settings['analytics'] ?? [];
$appearance = $settings['appearance'] ?? [];
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
            <a href="#discussion">Discussion</a>
            <a href="#analytics">Analytics</a>
            <a href="#appearance">Appearance</a>
            <a href="#account">Account &amp; Security</a>
            <a href="#system">System</a>
        </nav>

        <div class="settings-sections">
            <section id="publication" class="settings-section">
                <header>
                    <h2>Publication</h2>
                    <p class="quiet">Reader-facing identity and default authorship.</p>
                </header>
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
                <header>
                    <h2>Newsletter</h2>
                    <p class="quiet">Defaults for publication-to-email delivery.</p>
                </header>
                <p class="readiness"><strong>Ready</strong> — newsletter campaigns use the configured mail transport.</p>
                <form method="post" action="/dashboard/settings">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="section" value="newsletter">
                    <label for="newsletter-sender">Sender label</label>
                    <input id="newsletter-sender" name="sender_label" value="<?= e((string) ($newsletter['sender_label'] ?? '')) ?>">
                    <label class="checkbox-row"><input type="checkbox" name="publish_by_default" value="1"<?= !empty($newsletter['publish_by_default']) ? ' checked' : '' ?>> Include new posts by default</label>
                    <button type="submit">Save newsletter</button>
                </form>
            </section>

            <section id="discussion" class="settings-section">
                <header>
                    <h2>Discussion</h2>
                    <p class="quiet">Choose the default discussion provider without exposing credentials.</p>
                </header>
                <p class="readiness"><strong><?= ($discussion['provider'] ?? 'none') === 'none' ? 'Disabled' : 'Ready' ?></strong> — provider credentials remain deployment-only.</p>
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
                <header>
                    <h2>Analytics</h2>
                    <p class="quiet">Display preferences for privacy-bounded readership data.</p>
                </header>
                <p class="readiness"><strong>Ready</strong> — analytics storage remains separate from these preferences.</p>
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

            <section id="appearance" class="settings-section">
                <header>
                    <h2>Appearance</h2>
                    <p class="quiet">Publication-wide presentation defaults.</p>
                </header>
                <form method="post" action="/dashboard/settings">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="section" value="appearance">
                    <label for="appearance-theme">Theme</label>
                    <select id="appearance-theme" name="theme">
                        <?php foreach (['default' => 'Default', 'warm' => 'Warm', 'slate' => 'Slate'] as $value => $label): ?>
                            <option value="<?= e($value) ?>"<?= ($appearance['theme'] ?? 'default') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Save appearance</button>
                </form>
            </section>

            <section id="account" class="settings-section settings-readonly">
                <header>
                    <h2>Account &amp; Security</h2>
                    <p class="quiet">Unavailable here. Passwords, passkeys, sessions, and invitations remain in the authentication subsystem.</p>
                </header>
            </section>

            <section id="system" class="settings-section settings-readonly">
                <header>
                    <h2>System</h2>
                    <p class="quiet">Needs setup outside the dashboard. Deployment configuration, credentials, backups, and diagnostics remain machine-managed.</p>
                </header>
            </section>
        </div>
    </div>
</main>
</body>
</html>

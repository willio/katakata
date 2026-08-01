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
    <nav aria-label="Dashboard">
        <a href="/dashboard">Dashboard</a>
        <a href="/mail">Mail</a>
        <a class="button" href="/editor/new">New post</a>
        <a aria-current="page" href="/dashboard/settings">Settings</a>
    </nav>
</header>
<main class="dashboard-shell">
    <header class="dashboard-intro">
        <p class="eyebrow">Global configuration</p>
        <h1>Settings</h1>
        <p>Publication-wide defaults and integration preferences. Post-specific controls remain in the editor.</p>
    </header>

    <?php if ($saved): ?><p role="status">Settings saved.</p><?php endif; ?>
    <?php if ($error !== null): ?><p role="alert"><?= e($error) ?></p><?php endif; ?>

    <nav aria-label="Settings sections">
        <a href="#publication">Publication</a>
        <a href="#newsletter">Newsletter</a>
        <a href="#discussion">Discussion</a>
        <a href="#analytics">Analytics</a>
        <a href="#appearance">Appearance</a>
        <a href="#account">Account &amp; Security</a>
        <a href="#system">System</a>
    </nav>

    <section id="publication">
        <h2>Publication</h2>
        <form method="post" action="/dashboard/settings">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="section" value="publication">
            <label>Publication name <input name="name" required value="<?= e((string) ($publication['name'] ?? '')) ?>"></label>
            <label>Description <textarea name="description"><?= e((string) ($publication['description'] ?? '')) ?></textarea></label>
            <label>Default author <input name="default_author" value="<?= e((string) ($publication['default_author'] ?? '')) ?>"></label>
            <button type="submit">Save publication</button>
        </form>
    </section>

    <section id="newsletter">
        <h2>Newsletter</h2>
        <form method="post" action="/dashboard/settings">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="section" value="newsletter">
            <label>Sender label <input name="sender_label" value="<?= e((string) ($newsletter['sender_label'] ?? '')) ?>"></label>
            <label><input type="checkbox" name="publish_by_default" value="1"<?= !empty($newsletter['publish_by_default']) ? ' checked' : '' ?>> Include new posts by default</label>
            <button type="submit">Save newsletter</button>
        </form>
    </section>

    <section id="discussion">
        <h2>Discussion</h2>
        <form method="post" action="/dashboard/settings">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="section" value="discussion">
            <label>Provider
                <select name="provider">
                    <?php foreach (['none' => 'Disabled', 'native' => 'Native', 'threads' => 'Threads'] as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= ($discussion['provider'] ?? 'none') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><input type="checkbox" name="enabled_by_default" value="1"<?= !empty($discussion['enabled_by_default']) ? ' checked' : '' ?>> Enable discussion on new posts</label>
            <p class="quiet">Provider credentials remain deployment configuration and are not stored here.</p>
            <button type="submit">Save discussion</button>
        </form>
    </section>

    <section id="analytics">
        <h2>Analytics</h2>
        <form method="post" action="/dashboard/settings">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="section" value="analytics">
            <label>Dashboard period
                <select name="dashboard_period">
                    <?php foreach (['7d' => '7 days', '30d' => '30 days', '90d' => '90 days'] as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= ($analytics['dashboard_period'] ?? '30d') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit">Save analytics</button>
        </form>
    </section>

    <section id="appearance">
        <h2>Appearance</h2>
        <form method="post" action="/dashboard/settings">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="section" value="appearance">
            <label>Theme
                <select name="theme">
                    <?php foreach (['default' => 'Default', 'warm' => 'Warm', 'slate' => 'Slate'] as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= ($appearance['theme'] ?? 'default') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit">Save appearance</button>
        </form>
    </section>

    <section id="account"><h2>Account &amp; Security</h2><p>Manage sign-in and passkeys through the account security controls.</p></section>
    <section id="system"><h2>System</h2><p>Deployment configuration, credentials, backups, and diagnostics remain outside dashboard-managed settings.</p></section>
</main>
</body>
</html>

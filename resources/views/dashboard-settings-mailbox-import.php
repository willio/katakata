<?php
/** @var array<string,mixed> $user */
/** @var string $siteName */
/** @var list<array<string,mixed>> $candidates */
/** @var ?string $token */
/** @var ?string $error */
/** @var string $csrf */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Import mailbox — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="/assets/css/site.css">
    <link rel="stylesheet" href="/assets/css/boundary.css">
</head>
<body class="dashboard-page dashboard-settings-page">
<header class="dashboard-header">
    <a class="site-name" href="/dashboard"><?= e($siteName) ?></a>
    <nav aria-label="Mailbox import actions"><a href="/dashboard/settings/mailboxes">Mailbox accounts</a><a href="/dashboard/settings">Settings</a></nav>
</header>
<main class="dashboard-shell">
    <header class="dashboard-intro">
        <p class="eyebrow">Mailbox accounts</p>
        <h1>Import XML Mail profile</h1>
        <p>Upload an unsigned XML Apple <code>.mobileconfig</code> or XML plist. Signed CMS or binary profiles are not supported. Katakata extracts supported IMAP settings locally and never imports embedded passwords or identity material.</p>
    </header>

    <?php if ($error !== null): ?><p class="settings-feedback" role="alert"><?= e($error) ?></p><?php endif; ?>

    <?php if ($candidates === [] || $token === null): ?>
        <section class="settings-section">
            <form method="post" enctype="multipart/form-data" action="/dashboard/settings/mailboxes/import">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <label for="mail-profile">Unsigned XML configuration profile</label>
                <input id="mail-profile" type="file" name="profile" accept=".mobileconfig,.xml,application/xml,text/xml" required>
                <p class="quiet">Maximum 256 KiB. Signed CMS profiles, POP, certificates, private keys, and non-TLS incoming accounts are rejected.</p>
                <button type="submit">Review profile</button>
            </form>
        </section>
    <?php else: ?>
        <section class="settings-section">
            <header><h2>Imported candidates</h2><p class="quiet">Choose one account and map its deployment secret variable names before saving.</p></header>
            <?php foreach ($candidates as $index => $candidate): ?>
                <?php
                $suggested = strtolower(trim((string) ($candidate['label'] ?? 'mailbox')));
                $suggested = preg_replace('/[^a-z0-9_-]+/', '-', $suggested) ?? 'mailbox';
                $suggested = trim($suggested, '-_');
                if (strlen($suggested) < 2) { $suggested = 'mailbox'; }
                $suggested = substr($suggested, 0, 32);
                $secretPrefix = strtoupper(str_replace('-', '_', $suggested));
                ?>
                <article class="mail-readiness">
                    <h3><?= e((string) ($candidate['label'] ?? 'Mailbox')) ?></h3>
                    <dl class="settings-status-list">
                        <div><dt>Email</dt><dd><?= e((string) ($candidate['email_address'] ?? 'Not provided')) ?></dd></div>
                        <div><dt>Incoming host</dt><dd><?= e((string) ($candidate['incoming_host'] ?? '')) ?>:<?= e((string) ($candidate['incoming_port'] ?? 993)) ?></dd></div>
                        <div><dt>Encryption</dt><dd><?= e(strtoupper((string) ($candidate['incoming_encryption'] ?? 'ssl'))) ?></dd></div>
                        <div><dt>Imported username</dt><dd><?= e((string) ($candidate['incoming_username'] ?? 'Not provided')) ?></dd></div>
                        <div><dt>Outgoing host</dt><dd><?= e((string) ($candidate['outgoing_host'] ?? 'Not provided')) ?></dd></div>
                    </dl>
                    <?php if (!empty($candidate['embedded_credential_detected'])): ?><p><strong>Embedded credential detected.</strong> It has not been retained.</p><?php endif; ?>
                    <?php foreach ((array) ($candidate['warnings'] ?? []) as $warning): ?><p class="quiet"><?= e((string) $warning) ?></p><?php endforeach; ?>
                    <form method="post" action="/dashboard/settings/mailboxes/import/confirm">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <input type="hidden" name="candidate" value="<?= $index ?>">
                        <label for="account-id-<?= $index ?>">Account ID</label>
                        <input id="account-id-<?= $index ?>" name="id" required pattern="[a-z0-9][a-z0-9_-]{1,31}" value="<?= e($suggested) ?>">
                        <label for="username-secret-<?= $index ?>">Username secret variable</label>
                        <input id="username-secret-<?= $index ?>" name="username_secret" required pattern="[A-Z][A-Z0-9_]*" value="IMAP_<?= e($secretPrefix) ?>_USERNAME">
                        <label for="password-secret-<?= $index ?>">Password secret variable</label>
                        <input id="password-secret-<?= $index ?>" name="password_secret" required pattern="[A-Z][A-Z0-9_]*" value="IMAP_<?= e($secretPrefix) ?>_PASSWORD">
                        <button type="submit">Import this account</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>
</body>
</html>

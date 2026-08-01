<?php
/** @var array<string, mixed> $user */
/** @var string $siteName */
/** @var array{
 *   post: array{slug: string, title: string, published_at: string, author: ?string, excerpt: ?string, url: string},
 *   subject: string,
 *   preheader: string,
 *   canonical_url: string,
 *   recipient_count: int,
 *   recipients: list<array{email: string, confirmed_at: ?string}>,
 *   html: string,
 *   text: string,
 *   estimated_bytes: int,
 *   warnings: list<string>
 * } $proof
 */
/** @var string $csrf */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dispatch confirmation — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="dashboard-page mail-confirm-page">
<header class="dashboard-header">
    <a class="site-name" href="/dashboard"><?= e($siteName) ?></a>
    <nav aria-label="Dashboard">
        <a href="/dashboard">Dashboard</a>
        <a aria-current="page" href="/mail">Mail</a>
        <a class="button" href="/editor/new">New post</a>
        <a href="/editor">Settings</a>
    </nav>
</header>
<main class="dashboard-shell">
    <header class="dashboard-intro">
        <p class="eyebrow">Dispatch proof</p>
        <h1><?= e($proof['subject']) ?></h1>
        <p>Review the frozen campaign proof before creating queue entries.</p>
    </header>

    <section aria-labelledby="dispatch-summary">
        <h2 id="dispatch-summary">Delivery summary</h2>
        <dl>
            <div><dt>Subject</dt><dd><?= e($proof['subject']) ?></dd></div>
            <div><dt>Preheader</dt><dd><?= $proof['preheader'] !== '' ? e($proof['preheader']) : '<span class="quiet">Not set</span>' ?></dd></div>
            <div><dt>Published</dt><dd><?= e((new DateTimeImmutable($proof['post']['published_at']))->format('M j, Y')) ?></dd></div>
            <div><dt>Recipients</dt><dd><?= $proof['recipient_count'] ?></dd></div>
            <div><dt>Estimated size</dt><dd><?= number_format($proof['estimated_bytes']) ?> bytes</dd></div>
            <div><dt>Canonical URL</dt><dd><a href="<?= e($proof['canonical_url']) ?>"><?= e($proof['canonical_url']) ?></a></dd></div>
        </dl>
    </section>

    <?php if ($proof['warnings'] !== []): ?>
        <section aria-labelledby="campaign-warnings">
            <h2 id="campaign-warnings">Review warnings</h2>
            <ul class="dashboard-list">
                <?php foreach ($proof['warnings'] as $warning): ?>
                    <li><?= e($warning) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <section aria-labelledby="recipient-snapshot">
        <h2 id="recipient-snapshot">Recipient snapshot</h2>
        <?php if ($proof['recipients'] === []): ?>
            <p class="quiet">No confirmed recipients are currently eligible.</p>
        <?php else: ?>
            <ol class="dashboard-list mail-audience-list">
                <?php foreach (array_slice($proof['recipients'], 0, 10) as $recipient): ?>
                    <li>
                        <strong><?= e($recipient['email']) ?></strong>
                        <?php if ($recipient['confirmed_at'] !== null): ?>
                            <time datetime="<?= e($recipient['confirmed_at']) ?>">
                                Confirmed <?= e((new DateTimeImmutable($recipient['confirmed_at']))->format('M j, Y')) ?>
                            </time>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
            <?php if ($proof['recipient_count'] > 10): ?>
                <p class="quiet">Showing 10 of <?= $proof['recipient_count'] ?> recipients.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section aria-labelledby="html-proof">
        <h2 id="html-proof">HTML preview</h2>
        <article class="mail-proof-html"><?= $proof['html'] ?></article>
    </section>

    <section aria-labelledby="text-proof">
        <h2 id="text-proof">Plain text preview</h2>
        <pre><code><?= e($proof['text']) ?></code></pre>
    </section>

    <div class="form-actions">
        <a href="/mail?post=<?= rawurlencode($proof['post']['slug']) ?>">Back to Mail</a>
        <form method="post" action="/mail/confirm">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="post" value="<?= e($proof['post']['slug']) ?>">
            <button type="submit"<?= $proof['recipient_count'] === 0 ? ' disabled aria-disabled="true"' : '' ?>>Confirm &amp; Queue</button>
        </form>
    </div>

    <form class="form-actions" method="post" action="/logout">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <button type="submit">Sign out</button>
    </form>
</main>
</body>
</html>

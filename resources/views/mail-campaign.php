<?php
/** @var array<string, mixed> $user */
/** @var string $siteName */
/** @var \Katakata\Mail\Campaign $campaign */
/** @var array{
 *   campaign_id: string,
 *   total: int,
 *   pending: int,
 *   delivered: int,
 *   failed: int,
 *   abandoned: int,
 *   retryable: int,
 *   progress: int,
 *   status: string,
 *   started_at: ?string,
 *   completed_at: ?string,
 *   failures: list<array{recipient: string, error: string, attempts: int, status: string}>
 * } $delivery */
/** @var string $csrf */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Campaign <?= e($campaign->id) ?> — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="/assets/css/site.css">
    <link rel="stylesheet" href="/assets/css/boundary.css">
</head>
<body class="dashboard-page mail-campaign-page<?= ($buttonStyle ?? 'regular') === 'pill' ? ' buttons-pill' : '' ?>">
<header class="dashboard-header">
    <a class="site-name" href="/dashboard"><?= e($siteName) ?></a>
    <nav aria-label="Dashboard">
        <a href="/dashboard">Dashboard</a>
        <a aria-current="page" href="/mail">Mail</a>
        <a class="button" href="/editor/new">New post</a>
        <a href="/dashboard/settings">Settings</a>
    </nav>
</header>
<main class="dashboard-shell">
    <header class="dashboard-intro">
        <p class="eyebrow">Campaign <?= e(str_replace('_', ' ', $delivery['status'])) ?></p>
        <h1><?= e($campaign->subject) ?></h1>
        <p>The campaign snapshot is immutable. Delivery status is derived from its queue entries.</p>
        <form class="form-actions" method="post" action="/mail/campaign/<?= rawurlencode($campaign->id) ?>/drafts">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <button type="submit">Create from campaign</button>
        </form>
    </header>

    <section aria-labelledby="campaign-delivery">
        <h2 id="campaign-delivery">Delivery progress</h2>
        <p><strong><?= $delivery['progress'] ?>%</strong> resolved</p>
        <progress max="100" value="<?= $delivery['progress'] ?>"><?= $delivery['progress'] ?>%</progress>
        <dl>
            <div><dt>Status</dt><dd><?= e(str_replace('_', ' ', $delivery['status'])) ?></dd></div>
            <div><dt>Total</dt><dd><?= $delivery['total'] ?></dd></div>
            <div><dt>Pending</dt><dd><?= $delivery['pending'] ?></dd></div>
            <div><dt>Delivered</dt><dd><?= $delivery['delivered'] ?></dd></div>
            <div><dt>Retryable failures</dt><dd><?= $delivery['failed'] ?></dd></div>
            <div><dt>Abandoned</dt><dd><?= $delivery['abandoned'] ?></dd></div>
            <?php if ($delivery['started_at'] !== null): ?>
                <div><dt>Started</dt><dd><?= e((new DateTimeImmutable($delivery['started_at']))->format('M j, Y H:i')) ?></dd></div>
            <?php endif; ?>
            <?php if ($delivery['completed_at'] !== null): ?>
                <div><dt>Completed</dt><dd><?= e((new DateTimeImmutable($delivery['completed_at']))->format('M j, Y H:i')) ?></dd></div>
            <?php endif; ?>
        </dl>

        <?php if ($delivery['retryable'] > 0): ?>
            <form class="form-actions" method="post" action="/mail/campaign/<?= rawurlencode($campaign->id) ?>/retry">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <button type="submit">Retry <?= $delivery['retryable'] ?> failed <?= $delivery['retryable'] === 1 ? 'delivery' : 'deliveries' ?></button>
            </form>
        <?php endif; ?>
    </section>

    <?php if ($delivery['failures'] !== []): ?>
        <section aria-labelledby="campaign-failures">
            <h2 id="campaign-failures">Recent failures</h2>
            <ol class="dashboard-list">
                <?php foreach (array_slice($delivery['failures'], 0, 10) as $failure): ?>
                    <li>
                        <strong><?= e($failure['recipient']) ?></strong>
                        <p class="quiet"><?= e($failure['error']) ?></p>
                        <p class="quiet">
                            <?= $failure['attempts'] ?> <?= $failure['attempts'] === 1 ? 'attempt' : 'attempts' ?>
                            · <?= e($failure['status'] === 'abandoned' ? 'terminal' : 'retryable') ?>
                        </p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>

    <section aria-labelledby="campaign-summary">
        <h2 id="campaign-summary">Campaign summary</h2>
        <dl>
            <div><dt>ID</dt><dd><code><?= e($campaign->id) ?></code></dd></div>
            <div><dt>Post</dt><dd><?= e($campaign->postSlug) ?></dd></div>
            <div><dt>Recipients</dt><dd><?= $campaign->recipientCount() ?></dd></div>
            <div><dt>Confirmed</dt><dd><?= e($campaign->confirmedAt->format('M j, Y H:i')) ?></dd></div>
            <div><dt>Canonical URL</dt><dd><a href="<?= e($campaign->canonicalUrl) ?>"><?= e($campaign->canonicalUrl) ?></a></dd></div>
        </dl>
    </section>

    <section aria-labelledby="campaign-recipients">
        <h2 id="campaign-recipients">Frozen recipients</h2>
        <ol class="dashboard-list mail-audience-list">
            <?php foreach (array_slice($campaign->recipients, 0, 10) as $recipient): ?>
                <li><strong><?= e($recipient['email']) ?></strong></li>
            <?php endforeach; ?>
        </ol>
        <?php if ($campaign->recipientCount() > 10): ?>
            <p class="quiet">Showing 10 of <?= $campaign->recipientCount() ?> recipients.</p>
        <?php endif; ?>
    </section>

    <div class="form-actions">
        <a href="/mail/campaigns">Campaign history</a>
        <a href="/mail">Back to Mail</a>
    </div>

    <form class="form-actions" method="post" action="/logout">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <button type="submit">Sign out</button>
    </form>
</main>
</body>
</html>

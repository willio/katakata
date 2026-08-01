<?php
/** @var array<string, mixed> $user */
/** @var string $siteName */
/** @var list<array{campaign: \Katakata\Mail\Campaign, delivery: array<string, mixed>}> $campaigns */
/** @var string $csrf */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Campaign history — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="dashboard-page mail-campaigns-page">
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
        <p class="eyebrow">Delivery archive</p>
        <h1>Campaign history</h1>
        <p>Review immutable campaign snapshots and their current delivery state.</p>
    </header>

    <section aria-labelledby="campaign-history">
        <h2 id="campaign-history">Campaigns</h2>
        <?php if ($campaigns === []): ?>
            <p class="quiet">No campaigns have been confirmed yet.</p>
        <?php else: ?>
            <ol class="dashboard-list mail-review-list">
                <?php foreach ($campaigns as $entry): ?>
                    <?php $campaign = $entry['campaign']; $delivery = $entry['delivery']; ?>
                    <li>
                        <div>
                            <strong><?= e($campaign->subject) ?></strong>
                            <p class="quiet">
                                <?= e(str_replace('_', ' ', (string) $delivery['status'])) ?>
                                · <?= (int) $delivery['delivered'] ?>/<?= (int) $delivery['total'] ?> delivered
                                <?php if ((int) $delivery['failed'] > 0): ?>
                                    · <?= (int) $delivery['failed'] ?> failed
                                <?php endif; ?>
                            </p>
                        </div>
                        <div>
                            <time datetime="<?= e($campaign->confirmedAt->format(DATE_ATOM)) ?>">
                                <?= e($campaign->confirmedAt->format('M j, Y H:i')) ?>
                            </time>
                            <a href="/mail/campaign/<?= rawurlencode($campaign->id) ?>">View campaign</a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <div class="form-actions">
        <a href="/mail">Back to Mail</a>
    </div>

    <form class="form-actions" method="post" action="/logout">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <button type="submit">Sign out</button>
    </form>
</main>
</body>
</html>

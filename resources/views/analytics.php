<?php
/** @var string $siteName */
/** @var \Katakata\Analytics\AnalyticsSummary|null $analytics */
/** @var array<int, array{at: \DateTimeImmutable, page: string, referrer: ?string, region: ?string}> $recentVisits */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Analytics — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="dashboard-page">
<header class="dashboard-header">
    <a class="site-name" href="/dashboard"><?= e($siteName) ?></a>
    <nav aria-label="Analytics actions">
        <a href="/posts">Posts</a>
        <a href="/dashboard/settings">Settings</a>
    </nav>
</header>
<main class="dashboard-shell">
    <header class="dashboard-intro">
        <p class="eyebrow">Readership</p>
        <h1>Analytics</h1>
    </header>

    <?php if ($analytics === null): ?>
        <p class="quiet">Analytics is unavailable. Run <code>php bin/katakata analytics:check</code>.</p>
    <?php else: ?>
        <section class="dashboard-stats" aria-label="Analytics summary">
            <article><strong><?= e((string) $analytics->visits7d) ?></strong><span>Visits (7d)</span></article>
            <article><strong><?= e((string) $analytics->visits30d) ?></strong><span>Visits (30d)</span></article>
        </section>

        <section aria-labelledby="analytics-recent">
            <h2 id="analytics-recent">Recent visits</h2>
            <?php if ($recentVisits === []): ?>
                <p class="quiet">No visits recorded yet.</p>
            <?php else: ?>
                <ol class="dashboard-list">
                    <?php foreach ($recentVisits as $visit): ?>
                        <li>
                            <strong><?= e($visit['page']) ?></strong>
                            <time datetime="<?= e($visit['at']->format(DATE_ATOM)) ?>"><?= e($visit['at']->format('M j, H:i')) ?> UTC</time>
                            <?php if ($visit['referrer'] !== null): ?><span class="quiet">via <?= e(parse_url($visit['referrer'], PHP_URL_HOST) ?: 'direct') ?></span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
</body>
</html>

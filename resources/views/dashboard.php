<?php
/** @var array<string, mixed> $user */
/** @var string $siteName */
/** @var array<int, \Katakata\Content\Draft> $recentDrafts */
/** @var array<int, \Katakata\Content\Post> $latestPosts */
/** @var list<array{label:string,count:int|string,detail:?string,href:string}> $cards */
/** @var \Katakata\Analytics\AnalyticsSummary|null $analytics */
/** @var array<int, array{at: \DateTimeImmutable, page: string, referrer: ?string, region: ?string}> $recentVisits */
/** @var list<array{id: string, post_slug: string, text: string, username: string, timestamp: string, permalink: string, avatar_url: ?string}>|null $buzz */
/** @var string $csrf */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="dashboard-page">
<header class="dashboard-header">
    <a class="site-name" href="/dashboard"><?= e($siteName) ?></a>
    <nav aria-label="Dashboard actions">
        <a class="button" href="/editor/new">New post</a>
        <a href="/dashboard/settings">Settings</a>
    </nav>
</header>
<main class="dashboard-shell">
    <header class="dashboard-intro">
        <p class="eyebrow">Owner's view</p>
        <h1>Dashboard</h1>
        <p><?= e((string) $user['email']) ?></p>
    </header>

    <section class="dashboard-stats" aria-label="Site summary">
        <?php foreach ($cards as $card): ?>
            <a class="dashboard-stat-card" href="<?= e($card['href']) ?>">
                <strong><?= e((string) $card['count']) ?></strong>
                <span><?= e($card['label']) ?></span>
                <?php if ($card['detail'] !== null): ?><small><?= e($card['detail']) ?></small><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </section>

    <div class="dashboard-columns">
        <section aria-labelledby="recent-drafts">
            <h2 id="recent-drafts">Recent drafts</h2>
            <?php if ($recentDrafts === []): ?>
                <p class="quiet">Nothing in progress. <a href="/editor/new">Start a new post</a>.</p>
            <?php else: ?>
                <ol class="dashboard-list">
                    <?php foreach ($recentDrafts as $draft): ?>
                        <li>
                            <a href="/editor/drafts/<?= e($draft->slug) ?>"><?= e($draft->title) ?></a>
                            <?php if ($draft->updatedAt !== null): ?><time datetime="<?= e($draft->updatedAt->format(DATE_ATOM)) ?>"><?= e($draft->updatedAt->format('M j, Y')) ?></time><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>

        <section aria-labelledby="latest-posts">
            <h2 id="latest-posts">Latest posts</h2>
            <?php if ($latestPosts === []): ?>
                <p class="quiet">No published posts yet.</p>
            <?php else: ?>
                <ol class="dashboard-list">
                    <?php foreach ($latestPosts as $post): ?>
                        <li>
                            <a href="<?= e($post->url()) ?>"><?= e($post->title) ?></a>
                            <time datetime="<?= e($post->date->format('Y-m-d')) ?>"><?= e($post->date->format('M j, Y')) ?></time>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </div>

    <section class="dashboard-visits" aria-labelledby="recent-visits">
        <h2 id="recent-visits">Recent visits</h2>
        <?php if ($analytics === null): ?>
            <p class="quiet">Analytics is unavailable. Run <code>php bin/katakata analytics:check</code>.</p>
        <?php elseif ($recentVisits === []): ?>
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

    <section class="dashboard-buzz" aria-labelledby="dashboard-buzz">
        <h2 id="dashboard-buzz">The Buzz</h2>
        <?php if ($buzz === null): ?>
            <p class="quiet">Discussion replies are unavailable.</p>
        <?php elseif ($buzz === []): ?>
            <p class="quiet">No synced replies yet.</p>
        <?php else: ?>
            <ol class="dashboard-list">
                <?php foreach ($buzz as $reply): ?>
                    <li>
                        <a href="<?= e($reply['permalink']) ?>" rel="noreferrer">
                            <strong>@<?= e($reply['username']) ?></strong>
                            <span><?= e($reply['text']) ?></span>
                        </a>
                        <time datetime="<?= e($reply['timestamp']) ?>"><?= e((new DateTimeImmutable($reply['timestamp']))->format('M j, H:i')) ?> UTC</time>
                        <span class="quiet">on <?= e($reply['post_slug']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <form class="form-actions" method="post" action="/logout">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <button type="submit">Sign out</button>
    </form>
</main>
</body>
</html>

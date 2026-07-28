<?php
/** @var array<string, mixed> $user */
/** @var string $siteName */
/** @var array<int, \Katakata\Content\Draft> $recentDrafts */
/** @var array<int, \Katakata\Content\Post> $latestPosts */
/** @var int $publishedCount */
/** @var int $draftCount */
/** @var \Katakata\Seo\SeoCheckSummary $seo */
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
    <nav aria-label="Dashboard">
        <a class="button" href="/editor/new">New post</a>
        <a href="/editor">Settings</a>
    </nav>
</header>
<main class="dashboard-shell">
    <header class="dashboard-intro">
        <p class="eyebrow">Owner's view</p>
        <h1>Dashboard</h1>
        <p><?= e((string) $user['email']) ?></p>
    </header>

    <section class="dashboard-stats" aria-label="Site summary">
        <article><strong><?= $publishedCount ?></strong><span>Published posts</span></article>
        <article><strong><?= $draftCount ?></strong><span>Drafts in progress</span></article>
        <article>
            <strong><?= count($seo->issues) ?></strong>
            <span><?= $seo->passed() ? 'SEO checks clear' : 'SEO issues' ?></span>
        </article>
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

    <?php if (!$seo->passed()): ?>
        <section class="dashboard-seo" aria-labelledby="seo-issues">
            <h2 id="seo-issues">SEO checks</h2>
            <ul>
                <?php foreach ($seo->issues as $issue): ?>
                    <li><strong><?= e($issue->slug) ?></strong> — <?= e($issue->message) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <form class="form-actions" method="post" action="/logout">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <button type="submit">Sign out</button>
    </form>
</main>
</body>
</html>

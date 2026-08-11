<?php
/** @var string $name */
/** @var string $tagline */
/** @var string $siteUrl */
/** @var \Katakata\Content\Post|null $lead */
/** @var \Katakata\Content\Author|null $leadAuthor */
/** @var array<int, \Katakata\Content\Post> $recent */
/** @var array<string, array<int, \Katakata\Content\Post>> $earlierThisYear */
/** @var string|null $archiveYear */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($name) ?> — Independent publishing</title>
    <meta name="description" content="<?= e($tagline) ?>">
    <link rel="canonical" href="<?= e($siteUrl) ?>/">
    <link rel="alternate" type="application/rss+xml" title="<?= e($name) ?> RSS" href="/feed.xml">
    <link rel="alternate" type="application/feed+json" title="<?= e($name) ?> JSON Feed" href="/feed.json">
    <link rel="stylesheet" href="/assets/css/site.css">
    <link rel="stylesheet" href="/assets/css/home-redesign.css">
</head>
<body class="home-page">
    <header class="home-header">
        <a class="home-mark" href="/" aria-label="<?= e($name) ?> home"><?= e($name) ?></a>
        <nav aria-label="Primary">
            <a href="/archive">Archive</a>
            <a href="/newsletter">Newsletter</a>
        </nav>
    </header>

    <main class="home-editorial">
        <?php if ($lead === null): ?>
            <section class="home-empty" aria-labelledby="latest-writing">
                <p class="home-eyebrow">Latest</p>
                <h1 id="latest-writing">The first article is taking shape.</h1>
                <p>New writing will appear here when it is published.</p>
                <a href="/archive">Browse the archive</a>
            </section>
        <?php else: ?>
            <article class="home-lead" aria-labelledby="latest-writing">
                <p class="home-eyebrow">Latest</p>
                <h1 id="latest-writing"><a href="<?= e($lead->url()) ?>"><?= e($lead->title) ?></a></h1>
                <p class="home-lead-meta">
                    <time datetime="<?= e($lead->date->format('Y-m-d')) ?>"><?= e($lead->date->format('F j, Y')) ?></time>, by
                    <span class="home-lead-byline"><?php if ($leadAuthor !== null): ?><a href="/authors/<?= e($leadAuthor->slug) ?>"><?= e($leadAuthor->name) ?></a><?php else: ?><?= e($lead->author ?? $name) ?><?php endif; ?></span>
                </p>
            </article>

            <?php if ($recent !== []): ?>
                <section class="home-index" aria-labelledby="recent-writing">
                    <h2 class="home-index-heading" id="recent-writing">Recent</h2>
                    <ol>
                        <?php foreach ($recent as $post): ?>
                            <li>
                                <time class="home-index-date" datetime="<?= e($post->date->format('Y-m-d')) ?>"><?= e($post->date->format('F d')) ?></time>
                                <a class="home-index-title" href="<?= e($post->url()) ?>"><?= e($post->title) ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
            <?php endif; ?>

            <?php if ($earlierThisYear !== []): ?>
                <section class="home-months" aria-labelledby="earlier-this-year">
                    <h2 class="home-index-heading" id="earlier-this-year">Earlier This Year</h2>
                    <ol>
                        <?php foreach ($earlierThisYear as $monthPosts): ?>
                            <li>
                                <span class="home-month-label"><?= e($monthPosts[0]->date->format('M')) ?></span>
                                <span class="home-month-titles">
                                    <?php foreach ($monthPosts as $index => $post): ?>
                                        <?php if ($index > 0): ?><span class="home-title-separator" aria-hidden="true">·</span><?php endif; ?>
                                        <a href="<?= e($post->url()) ?>"><?= e($post->title) ?></a>
                                    <?php endforeach; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
            <?php endif; ?>

            <?php if ($archiveYear !== null): ?>
                <p class="home-previous-edition"><a href="/archive#year-<?= e($archiveYear) ?>">Earlier editions →</a></p>
            <?php endif; ?>
        <?php endif; ?>

        <form class="home-search" method="get" action="/archive" role="search">
            <label for="home-search-query">Search the archive</label>
            <input id="home-search-query" name="q" type="search" autocomplete="off">
        </form>
    </main>

    <footer class="home-footer">
        <p><?= e($name) ?></p>
        <nav aria-label="Feeds"><a href="/feed.xml">RSS</a><a href="/feed.json">JSON Feed</a></nav>
    </footer>
</body>
</html>

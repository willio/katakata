<?php
/** @var string $name */
/** @var string $tagline */
/** @var string $siteUrl */
/** @var \Katakata\Content\Post|null $lead */
/** @var \Katakata\Content\Author|null $leadAuthor */
/** @var array<string, array{label:string,posts:list<\Katakata\Content\Post>,has_more:bool,browse_url:string,show_author:bool}> $months */
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

            <?php if ($months !== []): ?>
                <section class="home-months" aria-labelledby="monthly-writing">
                    <h2 class="home-index-heading" id="monthly-writing">More writing</h2>
                    <?php foreach ($months as $shelf): ?>
                        <section class="home-month-shelf">
                            <h3><a href="<?= e($shelf['browse_url']) ?>"><?= e($shelf['label']) ?></a></h3>
                            <ol><?php foreach ($shelf['posts'] as $post): ?><li><a href="<?= e($post->url()) ?>"><?= e($post->title) ?></a><?php if (($shelf['show_author'] ?? false) && $post->author !== null): ?><span class="home-shelf-author"><?= e(mb_strtoupper($post->author)) ?></span><?php endif; ?></li><?php endforeach; ?></ol>
                            <?php if ($shelf['has_more']): ?><p><a href="<?= e($shelf['browse_url']) ?>">Browse <?= e(explode(' ', $shelf['label'])[0]) ?> →</a></p><?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                </section>
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

<?php
/** @var string $siteName */
/** @var array<int, array<int, \Katakata\Content\Post>> $years */
/** @var string $query */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $query === '' ? 'Archive' : 'Search' ?> — <?= e($siteName) ?></title>
    <link rel="alternate" type="application/rss+xml" title="<?= e($siteName) ?> RSS" href="/feed.xml">
    <link rel="alternate" type="application/feed+json" title="<?= e($siteName) ?> JSON Feed" href="/feed.json">
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="publication-page">
    <header class="site-header">
        <a class="site-name" href="/"><?= e($siteName) ?></a>
        <nav aria-label="Primary"><a href="/newsletter">Newsletter</a><a href="/feed.xml">RSS</a></nav>
    </header>
    <main class="page-shell archive-shell">
        <header class="page-header">
            <p class="eyebrow"><?= $query === '' ? 'All writing' : 'Search results' ?></p>
            <h1 class="publication-title"><?= $query === '' ? 'Archive' : 'Results for “' . e($query) . '”' ?></h1>
            <form class="archive-search" method="get" action="/archive" role="search">
                <label for="archive-query">Search editions</label>
                <input id="archive-query" name="q" type="search" value="<?= e($query) ?>" autocomplete="off">
            </form>
        </header>
        <?php if ($years === []): ?>
            <p><?= $query === '' ? 'No published articles yet.' : 'No articles matched your search.' ?></p>
        <?php else: ?>
            <?php foreach ($years as $year => $posts): ?>
                <section class="archive-year" aria-labelledby="year-<?= e($year) ?>">
                    <h2 id="year-<?= e($year) ?>"><?= e($year) ?></h2>
                    <ol class="post-list">
                        <?php foreach ($posts as $post): ?>
                            <li class="archive-entry">
                                <time class="archive-entry-date" datetime="<?= e($post->date->format('Y-m-d')) ?>"><?= e($post->date->format('Y m d')) ?></time>
                                <div class="archive-entry-copy">
                                    <h2><a class="publication-index-title" href="<?= e($post->url()) ?>"><?= e($post->title) ?></a></h2>
                                    <?php if ($post->excerpt !== null): ?><p><?= e($post->excerpt) ?></p><?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</body>
</html>

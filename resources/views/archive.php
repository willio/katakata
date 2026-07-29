<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Archive — <?= e($siteName) ?></title>
    <link rel="alternate" type="application/rss+xml" title="<?= e($siteName) ?> RSS" href="/feed.xml">
    <link rel="alternate" type="application/feed+json" title="<?= e($siteName) ?> JSON Feed" href="/feed.json">
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body>
    <header class="site-header">
        <a class="site-name" href="/"><?= e($siteName) ?></a>
        <nav aria-label="Primary"><a href="/feed.xml">RSS</a></nav>
    </header>
    <main class="page-shell">
        <header class="page-header">
            <p class="eyebrow">All writing</p>
            <h1>Archive</h1>
        </header>
        <?php if ($years === []): ?>
            <p>No published articles yet.</p>
        <?php else: ?>
            <?php foreach ($years as $year => $posts): ?>
                <section class="archive-year" aria-labelledby="year-<?= e($year) ?>">
                    <h2 id="year-<?= e($year) ?>"><?= e($year) ?></h2>
                    <ol class="post-list">
                        <?php foreach ($posts as $post): ?>
                            <li>
                                <time datetime="<?= e($post->date->format('Y-m-d')) ?>"><?= e($post->date->format('F j')) ?></time>
                                <h2><a href="<?= e($post->url()) ?>"><?= e($post->title) ?></a></h2>
                                <?php if ($post->excerpt !== null): ?><p><?= e($post->excerpt) ?></p><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</body>
</html>

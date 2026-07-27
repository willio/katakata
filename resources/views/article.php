<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($post->title) ?> — <?= e($siteName) ?></title>
    <?php if ($post->excerpt !== null): ?><meta name="description" content="<?= e($post->excerpt) ?>"><?php endif; ?>
    <link rel="alternate" type="application/rss+xml" title="<?= e($siteName) ?> RSS" href="/feed.xml">
    <link rel="alternate" type="application/feed+json" title="<?= e($siteName) ?> JSON Feed" href="/feed.json">
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body>
    <header class="site-header">
        <a class="site-name" href="/"><?= e($siteName) ?></a>
        <nav aria-label="Primary"><a href="/archive">Archive</a></nav>
    </header>
    <main class="article-shell">
        <article>
            <header class="article-header">
                <h1><?= e($post->title) ?></h1>
                <p class="article-meta">
                    <time datetime="<?= e($post->date->format('Y-m-d')) ?>"><?= e($post->date->format('F j, Y')) ?></time>
                    <?php if ($author !== null): ?>
                        · <a href="/authors/<?= e($author->slug) ?>"><?= e($author->name) ?></a>
                    <?php elseif ($post->author !== null): ?>
                        · <?= e($post->author) ?>
                    <?php endif; ?>
                </p>
            </header>
            <div class="article-body"><?= $bodyHtml ?></div>
        </article>
    </main>
</body>
</html>

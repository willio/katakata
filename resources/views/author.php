<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($author->name) ?> — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="publication-page">
    <header class="site-header">
        <a class="site-name" href="/"><?= e($siteName) ?></a>
        <nav aria-label="Primary"><a href="/archive">Archive</a></nav>
    </header>
    <main class="page-shell">
        <header class="page-header">
            <p class="eyebrow">Author</p>
            <h1 class="publication-title"><?= e($author->name) ?></h1>
            <?php if ($bioHtml !== null): ?>
                <div class="author-bio"><?= $bioHtml ?></div>
            <?php endif; ?>
        </header>
        <?php if ($posts === []): ?>
            <p>No published articles yet.</p>
        <?php else: ?>
            <ol class="post-list">
                <?php foreach ($posts as $post): ?>
                    <li>
                        <time datetime="<?= e($post->date->format('Y-m-d')) ?>"><?= e($post->date->format('F j, Y')) ?></time>
                        <h2><a class="publication-index-title" href="<?= e($post->url()) ?>"><?= e($post->title) ?></a></h2>
                        <?php if ($post->excerpt !== null): ?><p><?= e($post->excerpt) ?></p><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </main>
</body>
</html>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($name) ?></title>
    <link rel="alternate" type="application/rss+xml" title="<?= e($name) ?> RSS" href="/feed.xml">
    <link rel="alternate" type="application/feed+json" title="<?= e($name) ?> JSON Feed" href="/feed.json">
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body>
    <header class="site-header">
        <a class="site-name" href="/"><?= e($name) ?></a>
        <nav aria-label="Primary"><a href="/archive">Archive</a></nav>
    </header>
    <main class="page-shell home-shell">
        <p class="eyebrow">Independent publishing</p>
        <h1><?= e($name) ?></h1>
        <p class="tagline"><?= e($tagline) ?></p>
    </main>
</body>
</html>

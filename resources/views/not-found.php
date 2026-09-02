<?php
/** @var string|null $siteName */
$siteName ??= (string) env('APP_NAME', 'Katakata');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Page not found — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="publication-page">
    <header class="site-header">
        <a class="site-name" href="/"><?= e($siteName) ?></a>
        <nav aria-label="Primary"><a href="/archive">Archive</a><a href="/feed.xml">RSS</a></nav>
    </header>
    <main class="page-shell">
        <header class="page-header">
            <p class="eyebrow">Not found</p>
            <h1 class="publication-title">This page has wandered off.</h1>
        </header>
        <p>The page you are looking for does not exist or may have been moved.</p>
        <p><a href="/">Return to the homepage</a> or browse the <a href="/archive">archive</a>.</p>
    </main>
</body>
</html>

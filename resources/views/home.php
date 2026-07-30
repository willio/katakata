<?php
/** @var string $name */
/** @var string $tagline */
/** @var string $siteUrl */
/** @var array<int, \Katakata\Content\Post> $posts */
/** @var array<string, \Katakata\Content\Author|null> $authors */
/** @var string $csrf */
$lead = $posts[0] ?? null;
$more = array_slice($posts, 1);
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
</head>
<body>
    <header class="site-header">
        <a class="site-name" href="/"><?= e($name) ?></a>
        <nav aria-label="Primary">
            <a href="/archive">Archive</a>
            <a href="/feed.xml">RSS</a>
        </nav>
    </header>
    <main class="page-shell home-shell">
        <header class="home-intro">
            <p class="eyebrow">Independent publishing</p>
            <h1><?= e($name) ?></h1>
            <p class="tagline"><?= e($tagline) ?></p>
        </header>

        <?php if ($lead === null): ?>
            <section class="home-empty" aria-labelledby="latest-writing">
                <p class="eyebrow">Latest writing</p>
                <h2 id="latest-writing">The first article is taking shape.</h2>
                <p>New writing will appear here when it is published.</p>
                <a href="/archive">Browse the archive</a>
            </section>
        <?php else: ?>
            <section class="home-latest" aria-labelledby="latest-writing">
                <header class="section-heading">
                    <p class="eyebrow">Latest writing</p>
                    <a href="/archive">View all</a>
                </header>
                <article class="lead-story">
                    <p class="story-meta">
                        <time datetime="<?= e($lead->date->format('Y-m-d')) ?>"><?= e($lead->date->format('F j, Y')) ?></time>
                        <?php $leadAuthor = $authors[$lead->slug] ?? null; ?>
                        <?php if ($leadAuthor !== null): ?>
                            · <a href="/authors/<?= e($leadAuthor->slug) ?>"><?= e($leadAuthor->name) ?></a>
                        <?php elseif ($lead->author !== null): ?>
                            · <?= e($lead->author) ?>
                        <?php endif; ?>
                    </p>
                    <h2 id="latest-writing"><a href="<?= e($lead->url()) ?>"><?= e($lead->title) ?></a></h2>
                    <?php if ($lead->excerpt !== null): ?><p class="story-excerpt"><?= e($lead->excerpt) ?></p><?php endif; ?>
                    <a class="story-link" href="<?= e($lead->url()) ?>">Read article</a>
                </article>

                <?php if ($more !== []): ?>
                    <ol class="home-story-list">
                        <?php foreach ($more as $post): ?>
                            <?php $author = $authors[$post->slug] ?? null; ?>
                            <li>
                                <article>
                                    <p class="story-meta">
                                        <time datetime="<?= e($post->date->format('Y-m-d')) ?>"><?= e($post->date->format('F j, Y')) ?></time>
                                        <?php if ($author !== null): ?>
                                            · <a href="/authors/<?= e($author->slug) ?>"><?= e($author->name) ?></a>
                                        <?php elseif ($post->author !== null): ?>
                                            · <?= e($post->author) ?>
                                        <?php endif; ?>
                                    </p>
                                    <h3><a href="<?= e($post->url()) ?>"><?= e($post->title) ?></a></h3>
                                    <?php if ($post->excerpt !== null): ?><p><?= e($post->excerpt) ?></p><?php endif; ?>
                                </article>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <aside class="home-newsletter" aria-labelledby="newsletter-title">
            <div>
                <p class="eyebrow">Newsletter</p>
                <h2 id="newsletter-title">New writing, delivered quietly.</h2>
                <p>Receive each new article by email. No feed algorithms, no noise.</p>
            </div>
            <form method="post" action="/newsletter/subscribe">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <div class="field">
                    <label for="home-newsletter-email">Email</label>
                    <div class="field-control">
                        <input id="home-newsletter-email" name="email" type="email" autocomplete="email" placeholder="Email" required>
                    </div>
                </div>
                <div class="form-actions"><button class="button" type="submit">Subscribe</button></div>
                <p class="quiet">Confirm once. Unsubscribe whenever you like.</p>
            </form>
        </aside>
    </main>
    <footer class="site-footer">
        <p><?= e($name) ?></p>
        <nav aria-label="Feeds"><a href="/feed.xml">RSS</a><a href="/feed.json">JSON Feed</a></nav>
    </footer>
</body>
</html>

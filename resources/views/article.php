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
<body class="publication-page">
    <header class="site-header">
        <a class="site-name" href="/"><?= e($siteName) ?></a>
        <nav aria-label="Primary"><a href="/archive">Archive</a></nav>
    </header>
    <main class="article-shell">
        <article>
            <header class="article-header">
                <h1 class="publication-title"><?= e($post->title) ?></h1>
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
            <footer class="article-footer">
                <?php if ($author !== null): ?>
                    <p><strong><a href="/authors/<?= e($author->slug) ?>"><?= e($author->name) ?></a></strong></p>
                    <?php if ($authorBioHtml !== null): ?><div><?= $authorBioHtml ?></div><?php endif; ?>
                <?php endif; ?>
                <nav class="article-footer-nav" aria-label="Continue reading">
                    <a href="/archive">Archive</a>
                    <a href="/feed.xml">RSS</a>
                </nav>
            </footer>
        </article>
        <section id="discussion" class="article-discussion" aria-labelledby="discussion-heading">
            <header>
                <p class="eyebrow">Discussion</p>
                <h2 id="discussion-heading">Comments</h2>
                <p>Comments are reviewed before they appear publicly.</p>
            </header>
            <?php if ($commentState === 'pending'): ?>
                <p role="status">Your comment was submitted for review.</p>
            <?php elseif ($commentState === 'expired'): ?>
                <p role="alert">The comment form expired. Please try again.</p>
            <?php elseif ($commentState === 'invalid'): ?>
                <p role="alert">The comment could not be submitted. Check the required fields and try again.</p>
            <?php endif; ?>
            <?php if ($discussion['thread']->entries === []): ?>
                <p class="quiet">No approved comments yet.</p>
            <?php else: ?>
                <ol class="discussion-list">
                    <?php foreach ($discussion['thread']->entries as $entry): ?>
                        <li id="comment-<?= e($entry->id) ?>"<?= $entry->parentId !== null ? ' class="discussion-reply"' : '' ?>>
                            <article>
                                <header>
                                    <strong><?= e($entry->authorName) ?></strong>
                                    <time datetime="<?= e($entry->publishedAt->format(DATE_ATOM)) ?>"><?= e($entry->publishedAt->format('M j, Y H:i')) ?></time>
                                </header>
                                <p><?= nl2br(e($entry->body)) ?></p>
                                <a href="#comment-form" data-reply-to="<?= e($entry->id) ?>">Reply</a>
                            </article>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
            <form id="comment-form" method="post" action="<?= e($post->url()) ?>/discussion">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="parent_id" value="">
                <div hidden aria-hidden="true">
                    <label>Leave this field blank <input type="text" name="honeypot" tabindex="-1" autocomplete="off"></label>
                </div>
                <label>Name <input type="text" name="author_name" maxlength="120" required autocomplete="name"></label>
                <label>Comment <textarea name="body" rows="6" maxlength="5000" required></textarea></label>
                <button type="submit">Submit for review</button>
            </form>
        </section>
    </main>
    <script>
        document.querySelectorAll('[data-reply-to]').forEach((link) => {
            link.addEventListener('click', () => {
                const input = document.querySelector('#comment-form [name="parent_id"]');
                if (input) input.value = link.dataset.replyTo || '';
            });
        });
    </script>
</body>
</html>

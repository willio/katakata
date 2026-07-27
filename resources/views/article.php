<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($post->title) ?> — <?= e($siteName) ?></title>
    <?php if ($post->excerpt !== null): ?>
        <meta name="description" content="<?= e($post->excerpt) ?>">
    <?php endif; ?>
</head>
<body>
    <main>
        <article>
            <header>
                <h1><?= e($post->title) ?></h1>
                <p>
                    <time datetime="<?= e($post->date->format('Y-m-d')) ?>">
                        <?= e($post->date->format('F j, Y')) ?>
                    </time>
                    <?php if ($post->author !== null): ?>
                        · <?= e($post->author) ?>
                    <?php endif; ?>
                </p>
            </header>
            <div class="article-body">
                <?= $bodyHtml ?>
            </div>
        </article>
    </main>
</body>
</html>

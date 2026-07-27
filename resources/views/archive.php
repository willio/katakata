<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Archive — <?= e($siteName) ?></title>
</head>
<body>
    <main>
        <header>
            <h1>Archive</h1>
            <p>All published writing from <?= e($siteName) ?>.</p>
        </header>

        <?php if ($years === []): ?>
            <p>No published articles yet.</p>
        <?php else: ?>
            <?php foreach ($years as $year => $posts): ?>
                <section aria-labelledby="year-<?= e($year) ?>">
                    <h2 id="year-<?= e($year) ?>"><?= e($year) ?></h2>
                    <ol>
                        <?php foreach ($posts as $post): ?>
                            <li>
                                <time datetime="<?= e($post->date->format('Y-m-d')) ?>">
                                    <?= e($post->date->format('F j')) ?>
                                </time>
                                <a href="<?= e($post->url()) ?>"><?= e($post->title) ?></a>
                                <?php if ($post->excerpt !== null): ?>
                                    <p><?= e($post->excerpt) ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</body>
</html>

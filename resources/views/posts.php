<?php
/** @var string $siteName */
/** @var string $status */
/** @var array<int, \Katakata\Content\Draft> $drafts */
/** @var array<int, \Katakata\Content\Post> $posts */

$filters = [
    'all' => 'All',
    'drafts' => 'Drafts',
    'scheduled' => 'Scheduled',
    'published' => 'Published',
];

$rows = [];

if (in_array($status, ['all', 'drafts', 'scheduled'], true)) {
    foreach ($drafts as $draft) {
        $scheduledAt = is_string($draft->meta['scheduled_at'] ?? null)
            ? trim((string) $draft->meta['scheduled_at'])
            : '';
        $rowStatus = $scheduledAt === '' ? 'draft' : 'scheduled';

        if ($status !== 'all' && $status !== $rowStatus . 's' && !($status === 'scheduled' && $rowStatus === 'scheduled')) {
            continue;
        }

        $rows[] = [
            'title' => $draft->title,
            'status' => ucfirst($rowStatus),
            'author' => (string) ($draft->meta['author'] ?? '—'),
            'date' => $draft->updatedAt,
            'primaryHref' => '/editor/drafts/' . rawurlencode($draft->slug),
            'primaryLabel' => 'Edit',
            'secondaryHref' => null,
            'secondaryLabel' => null,
        ];
    }
}

if (in_array($status, ['all', 'published'], true)) {
    foreach ($posts as $post) {
        $rows[] = [
            'title' => $post->title,
            'status' => 'Published',
            'author' => $post->author ?? '—',
            'date' => $post->date,
            'primaryHref' => $post->url(),
            'primaryLabel' => 'View',
            'secondaryHref' => null,
            'secondaryLabel' => null,
        ];
    }
}

usort($rows, static function (array $left, array $right): int {
    $leftTime = $left['date'] instanceof DateTimeInterface ? $left['date']->getTimestamp() : 0;
    $rightTime = $right['date'] instanceof DateTimeInterface ? $right['date']->getTimestamp() : 0;

    return $rightTime <=> $leftTime;
});
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Posts — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="dashboard-page">
<header class="dashboard-header">
    <a class="site-name" href="/dashboard"><?= e($siteName) ?></a>
    <nav aria-label="Posts actions">
        <a class="button" href="/editor/new">New post</a>
        <a href="/dashboard/settings">Settings</a>
    </nav>
</header>
<main class="dashboard-shell">
    <header class="dashboard-intro">
        <p class="eyebrow">Editorial</p>
        <h1>Posts</h1>
    </header>

    <nav class="posts-filters" aria-label="Post status">
        <?php foreach ($filters as $key => $label): ?>
            <a href="/posts?status=<?= e($key) ?>"<?= $status === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($rows === []): ?>
        <p class="quiet">No posts match this filter.</p>
    <?php else: ?>
        <ol class="posts-index">
            <?php foreach ($rows as $row): ?>
                <li>
                    <div class="posts-index-main">
                        <strong><?= e($row['title']) ?></strong>
                        <span class="quiet"><?= e($row['status']) ?> · <?= e($row['author']) ?></span>
                    </div>
                    <time datetime="<?= e($row['date']->format(DATE_ATOM)) ?>"><?= e($row['date']->format('M j, Y')) ?></time>
                    <div class="posts-index-actions">
                        <a href="<?= e($row['primaryHref']) ?>"><?= e($row['primaryLabel']) ?></a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</main>
</body>
</html>

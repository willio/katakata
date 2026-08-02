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
$draftCount = 0;

foreach ($drafts as $draft) {
    $scheduledAt = is_string($draft->meta['scheduled_at'] ?? null)
        ? trim((string) $draft->meta['scheduled_at'])
        : '';

    if ($scheduledAt === '') {
        $draftCount++;
    }
}

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
            'titleHref' => '/editor/drafts/' . rawurlencode($draft->slug),
            'primaryHref' => null,
            'primaryLabel' => null,
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
            'titleHref' => $post->url(),
            'primaryHref' => null,
            'primaryLabel' => null,
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
            <?php $filterLabel = $key === 'drafts' ? 'Draft (' . $draftCount . ')' : $label; ?>
            <a href="/posts?status=<?= e($key) ?>"<?= $status === $key ? ' aria-current="page"' : '' ?>><?= e($filterLabel) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($rows === []): ?>
        <p class="quiet">No posts match this filter.</p>
    <?php else: ?>
        <ul class="posts-index">
            <?php foreach ($rows as $row): ?>
                <li>
                    <div class="posts-index-main">
                        <?php if ($row['titleHref'] !== null): ?>
                            <strong><a href="<?= e($row['titleHref']) ?>"><?= e($row['title']) ?></a></strong>
                        <?php else: ?>
                            <strong><?= e($row['title']) ?></strong>
                        <?php endif; ?>
                        <span class="quiet"><?= e($row['status']) ?> · <?= e($row['author']) ?></span>
                    </div>
                    <time datetime="<?= e($row['date']->format(DATE_ATOM)) ?>"><?= e($row['date']->format('M j, Y')) ?></time>
                    <?php if ($row['primaryHref'] !== null && $row['primaryLabel'] !== null): ?>
                        <div class="posts-index-actions">
                            <a href="<?= e($row['primaryHref']) ?>"><?= e($row['primaryLabel']) ?></a>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</main>
</body>
</html>

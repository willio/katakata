<?php
/** @var string $siteName */
/** @var string $status */
/** @var string $search */
/** @var array<int, \Katakata\Content\Draft> $drafts */
/** @var array<int, \Katakata\Content\Post> $posts */

$search ??= '';
$trashItems ??= [];
$canManagePublished ??= false;
$csrf ??= '';

$filters = [
    'all' => 'All',
    'drafts' => 'Drafts',
    'scheduled' => 'Scheduled',
    'published' => 'Published',
    'trash' => 'Trash',
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
            'href' => '/editor/drafts/' . rawurlencode($draft->slug),
            'type' => 'draft',
            'slug' => $draft->slug,
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
            'href' => $post->url(),
            'type' => 'post',
            'slug' => $post->slug,
        ];
    }
}

if ($status === 'trash') {
    foreach ($trashItems as $item) {
        if ($item->type === 'post' && !$canManagePublished) {
            continue;
        }
        $rows[] = [
            'title' => $item->title,
            'status' => ucfirst($item->type) . ' in Trash',
            'author' => $item->actorId,
            'date' => new DateTimeImmutable($item->trashedAt),
            'href' => null,
            'type' => $item->type,
            'trashId' => $item->id,
        ];
    }
}

usort($rows, static function (array $left, array $right): int {
    $leftTime = $left['date'] instanceof DateTimeInterface ? $left['date']->getTimestamp() : 0;
    $rightTime = $right['date'] instanceof DateTimeInterface ? $right['date']->getTimestamp() : 0;

    return $rightTime <=> $leftTime;
});

if ($search !== '') {
    $needle = function_exists('mb_strtolower') ? mb_strtolower($search) : strtolower($search);
    $rows = array_values(array_filter($rows, static function (array $row) use ($needle): bool {
        $haystack = implode(' ', [$row['title'], $row['author'], $row['status']]);
        $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack) : strtolower($haystack);
        return str_contains($haystack, $needle);
    }));
}

$groupedRows = [];
foreach ($rows as $row) {
    $year = $row['date'] instanceof DateTimeInterface ? $row['date']->format('Y') : 'Undated';
    $month = $row['date'] instanceof DateTimeInterface ? $row['date']->format('m') : '00';
    $groupedRows[$year][$month][] = $row;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Posts — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="/assets/css/site.css">
    <link rel="stylesheet" href="/assets/css/boundary.css">
    <link rel="stylesheet" href="/assets/css/posts.css">
</head>
<body class="dashboard-page posts-page<?= ($buttonStyle ?? 'regular') === 'pill' ? ' buttons-pill' : '' ?>">
<header class="dashboard-header owner-header">
    <a class="site-name" href="/dashboard"><?= e($siteName) ?></a>
    <nav aria-label="Posts actions">
        <a class="button" href="/editor/new">New post</a>
        <a href="/dashboard/settings">Settings</a>
    </nav>
</header>
<main class="dashboard-shell posts-shell">
    <header class="dashboard-intro posts-intro">
        <p class="eyebrow">Editorial</p>
        <h1>Posts</h1>
    </header>

    <nav class="posts-filters" aria-label="Post status">
        <?php foreach ($filters as $key => $label): ?>
            <?php $filterLabel = $key === 'drafts' ? 'Draft (' . $draftCount . ')' : $label; ?>
            <a href="/posts?status=<?= e($key) ?>"<?= $status === $key ? ' aria-current="page"' : '' ?>><?= e($filterLabel) ?></a>
        <?php endforeach; ?>
    </nav>

    <form class="posts-search" method="get" action="/posts" role="search">
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <label for="posts-search-query">Search posts</label>
        <input id="posts-search-query" type="search" name="q" value="<?= e($search) ?>" placeholder="Title, author, or status">
        <button type="submit">Search</button>
        <?php if ($search !== ''): ?><a href="/posts?status=<?= e($status) ?>">Clear</a><?php endif; ?>
    </form>

    <?php if ($rows === []): ?>
        <p class="quiet posts-empty">No posts match<?= $search !== '' ? ' “' . e($search) . '”' : ' this filter' ?>.</p>
    <?php else: ?>
        <div class="posts-archive">
        <?php foreach ($groupedRows as $year => $months): ?>
            <section class="posts-year" aria-labelledby="posts-year-<?= e($year) ?>">
                <h2 id="posts-year-<?= e($year) ?>"><?= e($year) ?></h2>
                <?php foreach ($months as $month => $monthRows): ?>
                    <?php $monthName = $month === '00' ? 'Undated' : DateTimeImmutable::createFromFormat('!m', $month)->format('F'); ?>
                    <section class="posts-month" aria-labelledby="posts-month-<?= e($year . '-' . $month) ?>">
                        <h3 id="posts-month-<?= e($year . '-' . $month) ?>"><?= e($monthName) ?></h3>
                        <ul class="posts-index">
            <?php foreach ($monthRows as $row): ?>
                <li>
                    <div class="posts-index-main">
                        <?php if ($row['href'] !== null): ?>
                            <a class="posts-index-title" href="<?= e($row['href']) ?>"><?= e($row['title']) ?></a>
                        <?php else: ?>
                            <span class="posts-index-title"><?= e($row['title']) ?></span>
                        <?php endif; ?>
                        <span class="posts-index-meta"><?= e($row['status']) ?> · <?= e($row['author']) ?></span>
                        <?php if (!isset($row['trashId'])): ?>
                            <div class="posts-index-actions" aria-label="Actions for <?= e($row['title']) ?>">
                                <?php if (($row['type'] ?? null) === 'draft'): ?>
                                    <a class="posts-row-action" href="<?= e($row['href']) ?>">Edit</a>
                                    <span aria-hidden="true">·</span>
                                    <form method="post" action="/editor/drafts/<?= e($row['slug']) ?>/trash" onsubmit="return confirm('Delete this draft? You can restore it from Trash.')">
                                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                        <button type="submit" class="posts-row-action">Delete</button>
                                    </form>
                                <?php elseif (($row['type'] ?? null) === 'post' && $canManagePublished): ?>
                                    <form method="post" action="/editor/posts/<?= e($row['slug']) ?>/trash" onsubmit="return confirm('Delete this published article? Its public URL and listings will disappear until restored from Trash.')">
                                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                        <button type="submit" class="posts-row-action">Delete</button>
                                    </form>
                                    <span aria-hidden="true">·</span>
                                    <form method="post" action="/editor/posts/<?= e($row['slug']) ?>/campaign-drafts">
                                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                        <button type="submit" class="posts-row-action">Send as newsletter</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <time datetime="<?= e($row['date']->format(DATE_ATOM)) ?>"><?= e($row['date']->format('M j, Y')) ?></time>
                    <?php if (isset($row['trashId'])): ?>
                        <form method="post" action="/editor/trash/<?= e($row['type']) ?>/<?= e($row['trashId']) ?>/restore">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <button type="submit">Restore</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>

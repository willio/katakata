<?php
/** @var array<string, mixed> $user */
/** @var string $siteName */
/** @var list<array{slug: string, title: string, published_at: string, author: ?string, excerpt: ?string, url: string}> $queue */
/** @var array{count: int, recipients: list<array{email: string, confirmed_at: ?string}>} $audience */
/** @var array{post: array{slug: string, title: string, published_at: string, author: ?string, excerpt: ?string, url: string}, recipient_count: int}|null $campaign */
/** @var string $csrf */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mail — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="dashboard-page mail-page">
<header class="dashboard-header">
    <a class="site-name" href="/dashboard"><?= e($siteName) ?></a>
    <nav aria-label="Dashboard">
        <a href="/dashboard">Dashboard</a>
        <a aria-current="page" href="/mail">Mail</a>
        <a class="button" href="/editor/new">New post</a>
        <a href="/editor">Settings</a>
    </nav>
</header>
<main class="dashboard-shell">
    <header class="dashboard-intro">
        <p class="eyebrow">Distribution review</p>
        <h1>Mail</h1>
        <p>Review newsletter candidates and the confirmed audience. Nothing is sent from this page.</p>
        <div class="form-actions">
            <a href="/mail/campaigns">Campaign history</a>
        </div>
    </header>

    <section aria-labelledby="mail-audience">
        <h2 id="mail-audience">Audience preview</h2>
        <p><strong><?= $audience['count'] ?></strong> confirmed <?= $audience['count'] === 1 ? 'recipient' : 'recipients' ?></p>
        <?php if ($audience['recipients'] === []): ?>
            <p class="quiet">No confirmed subscribers are currently eligible for delivery.</p>
        <?php else: ?>
            <ol class="dashboard-list mail-audience-list">
                <?php foreach ($audience['recipients'] as $recipient): ?>
                    <li>
                        <strong><?= e($recipient['email']) ?></strong>
                        <?php if ($recipient['confirmed_at'] !== null): ?>
                            <time datetime="<?= e($recipient['confirmed_at']) ?>">
                                Confirmed <?= e((new DateTimeImmutable($recipient['confirmed_at']))->format('M j, Y')) ?>
                            </time>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <section aria-labelledby="mail-campaign-preview">
        <h2 id="mail-campaign-preview">Campaign preview</h2>
        <?php if ($campaign === null): ?>
            <p class="quiet">Select a newsletter candidate to review its campaign details.</p>
        <?php else: ?>
            <article>
                <p class="eyebrow">Selected campaign</p>
                <h3><?= e($campaign['post']['title']) ?></h3>
                <?php if ($campaign['post']['excerpt'] !== null && $campaign['post']['excerpt'] !== ''): ?>
                    <p><?= e($campaign['post']['excerpt']) ?></p>
                <?php endif; ?>
                <p class="quiet">
                    <?= $campaign['recipient_count'] ?> confirmed <?= $campaign['recipient_count'] === 1 ? 'recipient' : 'recipients' ?>
                    <?php if ($campaign['post']['author'] !== null && $campaign['post']['author'] !== ''): ?>
                        · <?= e($campaign['post']['author']) ?>
                    <?php endif; ?>
                </p>
                <div class="form-actions">
                    <a href="<?= e($campaign['post']['url']) ?>">View post</a>
                    <a class="button" href="/mail/confirm?post=<?= rawurlencode($campaign['post']['slug']) ?>">Review dispatch proof</a>
                </div>
            </article>
        <?php endif; ?>
    </section>

    <section aria-labelledby="mail-review-queue">
        <h2 id="mail-review-queue">Newsletter review</h2>
        <?php if ($queue === []): ?>
            <div class="quiet">
                <p>No newsletter candidates.</p>
                <p>Publish a post with <code>publish_as_newsletter: true</code> to review it here.</p>
            </div>
        <?php else: ?>
            <ol class="dashboard-list mail-review-list">
                <?php foreach ($queue as $candidate): ?>
                    <li>
                        <div>
                            <strong><?= e($candidate['title']) ?></strong>
                            <?php if ($candidate['excerpt'] !== null && $candidate['excerpt'] !== ''): ?>
                                <p class="quiet"><?= e($candidate['excerpt']) ?></p>
                            <?php endif; ?>
                            <p class="quiet">
                                <span>Newsletter</span>
                                <?php if ($candidate['author'] !== null && $candidate['author'] !== ''): ?>
                                    <span> · <?= e($candidate['author']) ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div>
                            <time datetime="<?= e($candidate['published_at']) ?>">
                                <?= e((new DateTimeImmutable($candidate['published_at']))->format('M j, Y')) ?>
                            </time>
                            <a href="/mail?post=<?= rawurlencode($candidate['slug']) ?>">Preview campaign</a>
                            <a href="<?= e($candidate['url']) ?>">View post</a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <form class="form-actions" method="post" action="/logout">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <button type="submit">Sign out</button>
    </form>
</main>
</body>
</html>

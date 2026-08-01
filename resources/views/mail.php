<?php
/** @var array<string, mixed> $user */
/** @var string $siteName */
/** @var 'inbox'|'campaigns' $area */
/** @var array{reader:int,campaigns:int,total:int,detail:string} $attention */
/** @var array{status:string,reason:?string,last_synced_at:?string} $mailboxReadiness */
/** @var list<\Katakata\Email\MessageSummary> $messages */
/** @var list<\Katakata\Email\Draft> $drafts */
/** @var list<array{slug: string, title: string, published_at: string, author: ?string, excerpt: ?string, url: string}> $queue */
/** @var array{count: int, recipients: list<array{email: string, confirmed_at: ?string}>} $audience */
/** @var array{post: array{slug: string, title: string, published_at: string, author: ?string, excerpt: ?string, url: string}, recipient_count: int}|null $campaign */
/** @var bool $newsletterReady */
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
    <nav aria-label="Mail actions">
        <a class="button" href="/mail/compose">Compose</a>
        <a href="/dashboard/settings">Settings</a>
    </nav>
</header>
<main class="dashboard-shell">
    <header class="dashboard-intro">
        <p class="eyebrow">Editorial correspondence</p>
        <h1>Mail</h1>
        <p><?= e($attention['detail']) ?></p>
    </header>

    <nav class="mail-area-nav" aria-label="Mail workspace">
        <a href="/mail?area=inbox"<?= $area === 'inbox' ? ' aria-current="page"' : '' ?>>Inbox<?= $attention['reader'] > 0 ? ' (' . $attention['reader'] . ')' : '' ?></a>
        <a href="/mail?area=campaigns"<?= $area === 'campaigns' ? ' aria-current="page"' : '' ?>>Campaigns<?= $attention['campaigns'] > 0 ? ' (' . $attention['campaigns'] . ')' : '' ?></a>
    </nav>

    <?php if ($mailboxReadiness['status'] !== 'ready'): ?>
        <section class="mail-readiness" role="status" aria-labelledby="mail-readiness-title">
            <h2 id="mail-readiness-title">Inbox needs setup</h2>
            <p><?= e((string) ($mailboxReadiness['reason'] ?? 'Configure the deployment mailbox adapter to enable reader correspondence.')) ?></p>
            <p class="quiet">Campaign work remains available. Inbox credentials are deployment-only and are never shown here.</p>
        </section>
    <?php endif; ?>

    <?php if ($area === 'inbox'): ?>
        <section aria-labelledby="mail-inbox">
            <h2 id="mail-inbox">Inbox</h2>
            <?php if ($mailboxReadiness['status'] !== 'ready'): ?>
                <p class="quiet">The inbox is unavailable until the scheduled mailbox sync is configured.</p>
            <?php elseif ($messages === []): ?>
                <p class="quiet">No reader messages.</p>
            <?php else: ?>
                <ol class="dashboard-list">
                    <?php foreach ($messages as $message): ?>
                        <li>
                            <a href="/mail/messages/<?= rawurlencode($message->id) ?>"><strong><?= e($message->subject) ?></strong></a>
                            <span><?= e($message->from) ?><?= $message->unread ? ' · Unread' : '' ?></span>
                            <time datetime="<?= e($message->receivedAt->format(DATE_ATOM)) ?>"><?= e($message->receivedAt->format('M j, H:i')) ?></time>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>

        <section aria-labelledby="mail-drafts">
            <h2 id="mail-drafts">Drafts</h2>
            <?php if ($drafts === []): ?>
                <p class="quiet">No saved correspondence drafts.</p>
            <?php else: ?>
                <ol class="dashboard-list">
                    <?php foreach ($drafts as $draft): ?>
                        <li>
                            <a href="/mail/drafts/<?= rawurlencode($draft->id) ?>"><strong><?= e($draft->subject !== '' ? $draft->subject : 'Untitled draft') ?></strong></a>
                            <span><?= e($draft->to !== '' ? $draft->to : 'No recipient') ?></span>
                            <time datetime="<?= e($draft->updatedAt->format(DATE_ATOM)) ?>"><?= e($draft->updatedAt->format('M j, H:i')) ?></time>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <?php if (!$newsletterReady): ?>
            <section class="mail-readiness" role="status" aria-labelledby="newsletter-readiness-title">
                <h2 id="newsletter-readiness-title">Newsletter needs setup</h2>
                <p>Configure NEWSLETTER_SECRET or APP_KEY before subscriptions and campaign dispatch are available.</p>
            </section>
        <?php endif; ?>

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
                                <time datetime="<?= e($recipient['confirmed_at']) ?>">Confirmed <?= e((new DateTimeImmutable($recipient['confirmed_at']))->format('M j, Y')) ?></time>
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
                    <p class="quiet"><?= $campaign['recipient_count'] ?> confirmed <?= $campaign['recipient_count'] === 1 ? 'recipient' : 'recipients' ?></p>
                    <div class="form-actions">
                        <a href="<?= e($campaign['post']['url']) ?>">View post</a>
                        <?php if ($newsletterReady): ?><a class="button" href="/mail/confirm?post=<?= rawurlencode($campaign['post']['slug']) ?>">Review dispatch proof</a><?php endif; ?>
                    </div>
                </article>
            <?php endif; ?>
        </section>

        <section aria-labelledby="mail-review-queue">
            <h2 id="mail-review-queue">Newsletter review</h2>
            <?php if ($queue === []): ?>
                <p class="quiet">No newsletter candidates.</p>
            <?php else: ?>
                <ol class="dashboard-list mail-review-list">
                    <?php foreach ($queue as $candidate): ?>
                        <li>
                            <div>
                                <strong><?= e($candidate['title']) ?></strong>
                                <?php if ($candidate['excerpt'] !== null && $candidate['excerpt'] !== ''): ?><p class="quiet"><?= e($candidate['excerpt']) ?></p><?php endif; ?>
                            </div>
                            <div>
                                <time datetime="<?= e($candidate['published_at']) ?>"><?= e((new DateTimeImmutable($candidate['published_at']))->format('M j, Y')) ?></time>
                                <a href="/mail?area=campaigns&amp;post=<?= rawurlencode($candidate['slug']) ?>">Preview campaign</a>
                                <a href="<?= e($candidate['url']) ?>">View post</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
            <p><a href="/mail/campaigns">Campaign history</a></p>
        </section>
    <?php endif; ?>

    <form class="form-actions" method="post" action="/logout">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <button type="submit">Sign out</button>
    </form>
</main>
</body>
</html>

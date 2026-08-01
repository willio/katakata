<?php
/** @var array<string, mixed> $user */
/** @var string $siteName */
/** @var 'inbox'|'campaigns' $area */
/** @var array{reader:int,campaigns:int,total:int,detail:string} $attention */
/** @var array{status:string,reason:?string,last_synced_at:?string} $mailboxReadiness */
/** @var list<\Katakata\Email\MessageSummary> $messages */
/** @var list<\Katakata\Email\Draft> $drafts */
/** @var list<\Katakata\Mail\CampaignDraft> $campaignDrafts */
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
<main class="mail-workspace-shell">
    <aside class="mail-sidebar" aria-label="Mail destinations">
        <section>
            <p class="eyebrow">Mail</p>
            <a href="/mail?area=inbox"<?= $area === 'inbox' ? ' aria-current="page"' : '' ?>>Inbox<?= $attention['reader'] > 0 ? ' (' . $attention['reader'] . ')' : '' ?></a>
            <a href="/mail?area=inbox#mail-drafts">Draft replies</a>
            <a href="/mail?area=inbox#mail-archive">Archive</a>
        </section>
        <section>
            <p class="eyebrow">Newsletter</p>
            <a href="/mail?area=campaigns"<?= $area === 'campaigns' ? ' aria-current="page"' : '' ?>>Campaigns<?= $attention['campaigns'] > 0 ? ' (' . $attention['campaigns'] . ')' : '' ?></a>
            <a href="/mail?area=campaigns#campaign-drafts">Draft campaigns</a>
            <a href="/mail/campaigns">Sent campaigns</a>
        </section>
        <section>
            <a href="/dashboard/settings">Settings</a>
        </section>
    </aside>

    <section class="mail-list-panel" aria-labelledby="mail-list-title">
        <header class="mail-panel-header">
            <p class="eyebrow">Editorial correspondence</p>
            <h1 id="mail-list-title"><?= $area === 'inbox' ? 'Inbox' : 'Campaigns' ?></h1>
            <p><?= e($attention['detail']) ?></p>
        </header>

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
                    <ol class="mail-item-list">
                        <?php foreach ($messages as $message): ?>
                            <li>
                                <a href="/mail/messages/<?= rawurlencode($message->id) ?>">
                                    <strong><?= e($message->subject) ?></strong>
                                    <span><?= e($message->from) ?><?= $message->unread ? ' · Unread' : '' ?></span>
                                    <time datetime="<?= e($message->receivedAt->format(DATE_ATOM)) ?>"><?= e($message->receivedAt->format('M j, H:i')) ?></time>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>

            <section id="mail-drafts" aria-labelledby="mail-drafts-title">
                <h2 id="mail-drafts-title">Draft replies</h2>
                <?php if ($drafts === []): ?>
                    <p class="quiet">No saved correspondence drafts.</p>
                <?php else: ?>
                    <ol class="mail-item-list">
                        <?php foreach ($drafts as $draft): ?>
                            <li>
                                <a href="/mail/drafts/<?= rawurlencode($draft->id) ?>">
                                    <strong><?= e($draft->subject !== '' ? $draft->subject : 'Untitled draft') ?></strong>
                                    <span><?= e($draft->to !== '' ? $draft->to : 'No recipient') ?></span>
                                    <time datetime="<?= e($draft->updatedAt->format(DATE_ATOM)) ?>"><?= e($draft->updatedAt->format('M j, H:i')) ?></time>
                                </a>
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

            <section id="campaign-drafts" aria-labelledby="campaign-drafts-title">
                <h2 id="campaign-drafts-title">Draft campaigns</h2>
                <?php if ($campaignDrafts === []): ?>
                    <p class="quiet">No campaign drafts yet.</p>
                <?php else: ?>
                    <ol class="mail-item-list">
                        <?php foreach ($campaignDrafts as $draft): ?>
                            <li>
                                <a href="/mail/campaign-drafts/<?= rawurlencode($draft->id) ?>">
                                    <strong><?= e($draft->subject !== '' ? $draft->subject : 'Untitled campaign') ?></strong>
                                    <span><?= e($draft->sourceType === 'post' ? 'From post ' . (string) $draft->sourceId : 'Campaign draft') ?></span>
                                    <time datetime="<?= e($draft->updatedAt->format(DATE_ATOM)) ?>"><?= e($draft->updatedAt->format('M j, H:i')) ?></time>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>

            <section aria-labelledby="mail-review-queue">
                <h2 id="mail-review-queue">Newsletter review</h2>
                <?php if ($queue === []): ?>
                    <p class="quiet">No newsletter candidates.</p>
                <?php else: ?>
                    <ol class="mail-item-list">
                        <?php foreach ($queue as $candidate): ?>
                            <li>
                                <a href="/mail?area=campaigns&amp;post=<?= rawurlencode($candidate['slug']) ?>">
                                    <strong><?= e($candidate['title']) ?></strong>
                                    <span><?= e((string) ($candidate['author'] ?? '—')) ?></span>
                                    <time datetime="<?= e($candidate['published_at']) ?>"><?= e((new DateTimeImmutable($candidate['published_at']))->format('M j, Y')) ?></time>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </section>

    <section class="mail-detail-panel" aria-labelledby="mail-detail-title">
        <?php if ($area === 'campaigns'): ?>
            <header class="mail-panel-header">
                <p class="eyebrow">Newsletter</p>
                <h2 id="mail-detail-title">Campaign detail</h2>
            </header>
            <section aria-labelledby="mail-audience">
                <h3 id="mail-audience">Audience now</h3>
                <p><strong><?= $audience['count'] ?></strong> confirmed <?= $audience['count'] === 1 ? 'recipient' : 'recipients' ?></p>
                <p class="quiet">This count is informational only. The recipient set is snapshotted when a reviewed campaign is confirmed and queued.</p>
            </section>
            <section aria-labelledby="mail-campaign-preview">
                <h3 id="mail-campaign-preview">Selected candidate</h3>
                <?php if ($campaign === null): ?>
                    <p class="quiet">Select a campaign draft or newsletter candidate from the center list.</p>
                <?php else: ?>
                    <article>
                        <h3><?= e($campaign['post']['title']) ?></h3>
                        <?php if ($campaign['post']['excerpt'] !== null && $campaign['post']['excerpt'] !== ''): ?>
                            <p><?= e($campaign['post']['excerpt']) ?></p>
                        <?php endif; ?>
                        <div class="form-actions">
                            <a href="<?= e($campaign['post']['url']) ?>">View post</a>
                            <?php if ($newsletterReady): ?><a class="button" href="/mail/confirm?post=<?= rawurlencode($campaign['post']['slug']) ?>">Review dispatch proof</a><?php endif; ?>
                        </div>
                    </article>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <header class="mail-panel-header">
                <p class="eyebrow">Reader mail</p>
                <h2 id="mail-detail-title">Message detail</h2>
            </header>
            <p class="quiet">Select a message or draft from the center list. Compose opens the correspondence editor without changing this workspace state.</p>
            <div class="form-actions"><a class="button" href="/mail/compose">Compose mail</a></div>
        <?php endif; ?>

        <form class="form-actions" method="post" action="/logout">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <button type="submit">Sign out</button>
        </form>
    </section>
</main>
</body>
</html>

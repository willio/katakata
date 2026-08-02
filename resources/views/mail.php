<?php
/** @var array<string, mixed> $user */
/** @var string $siteName */
/** @var 'inbox'|'campaigns' $area */
/** @var array{reader:int,campaigns:int,total:int,detail:string} $attention */
/** @var array<string,mixed> $mailboxReadiness */
/** @var list<\Katakata\Email\MessageSummary> $messages */
/** @var list<\Katakata\Email\Draft> $drafts */
/** @var ?\Katakata\Email\Draft $selectedDraft */
/** @var list<\Katakata\Mail\CampaignDraft> $campaignDrafts */
/** @var list<array{slug:string,title:string,published_at:string,author:?string,excerpt:?string,url:string}> $queue */
/** @var array{count:int,recipients:list<array{email:string,confirmed_at:?string}>} $audience */
/** @var array{post:array{slug:string,title:string,published_at:string,author:?string,excerpt:?string,url:string},recipient_count:int}|null $campaign */
/** @var bool $newsletterReady */
/** @var string $csrf */
/** @var string $composeError */

$accountStates = array_values(array_filter(
    (array) ($mailboxReadiness['accounts'] ?? []),
    static fn (mixed $state): bool => is_array($state) && isset($state['account_id'], $state['label']),
));
$selectedAccount = trim((string) ($_GET['account'] ?? 'all'));
$knownAccounts = array_column($accountStates, 'account_id');
if ($selectedAccount !== 'all' && !in_array($selectedAccount, $knownAccounts, true)) {
    $selectedAccount = 'all';
}
if ($selectedAccount !== 'all') {
    $messages = array_values(array_filter(
        $messages,
        static fn (\Katakata\Email\MessageSummary $message): bool => $message->sourceAccountId === $selectedAccount,
    ));
}
$inboxQuery = static fn (string $account): string => '/mail?area=inbox&amp;account=' . rawurlencode($account);
$selectedLabel = 'Inbox';
foreach ($accountStates as $accountState) {
    if ((string) $accountState['account_id'] === $selectedAccount) {
        $selectedLabel = (string) $accountState['label'];
        break;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mail — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="/assets/css/site.css">
    <link rel="stylesheet" href="/assets/css/mail.css">
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
            <a href="/mail?area=inbox"<?= $area === 'inbox' && $selectedAccount === 'all' ? ' aria-current="page"' : '' ?>>Inbox<?= $attention['reader'] > 0 ? ' (' . $attention['reader'] . ')' : '' ?></a>
            <?php if ($accountStates !== []): ?>
                <nav class="mail-account-nav" aria-label="Inbox accounts">
                    <a href="<?= $inboxQuery('all') ?>"<?= $selectedAccount === 'all' ? ' aria-current="page"' : '' ?>>All accounts</a>
                    <?php foreach ($accountStates as $accountState): ?>
                        <?php $accountStatus = (string) ($accountState['status'] ?? 'needs_setup'); ?>
                        <a href="<?= $inboxQuery((string) $accountState['account_id']) ?>"<?= $selectedAccount === $accountState['account_id'] ? ' aria-current="page"' : '' ?>>
                            <?= e((string) $accountState['label']) ?>
                            <?php if ($accountStatus !== 'ready'): ?><span class="quiet"> · <?= $accountStatus === 'error' ? 'Needs attention' : 'Needs setup' ?></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
            <a href="/mail?area=inbox#mail-drafts">Draft replies</a>
            <a href="/mail/sent">Sent mail</a>
            <a href="/mail/archive">Archive</a>
        </section>
        <section>
            <p class="eyebrow">Newsletter</p>
            <a href="/mail?area=campaigns"<?= $area === 'campaigns' ? ' aria-current="page"' : '' ?>>Campaigns<?= $attention['campaigns'] > 0 ? ' (' . $attention['campaigns'] . ')' : '' ?></a>
            <a href="/mail?area=campaigns#campaign-drafts">Draft campaigns</a>
            <a href="/mail/campaigns">Sent campaigns</a>
        </section>
        <section><a href="/dashboard/settings">Settings</a></section>
    </aside>

    <section class="mail-list-panel" aria-labelledby="mail-list-title">
        <header class="mail-panel-header">
            <p class="eyebrow">Editorial correspondence</p>
            <h1 id="mail-list-title"><?= $area === 'inbox' ? e($selectedAccount === 'all' ? 'Inbox' : $selectedLabel) : 'Campaigns' ?></h1>
            <p><?= e($attention['detail']) ?></p>
        </header>

        <?php if (!in_array($mailboxReadiness['status'], ['ready', 'disabled'], true)): ?>
            <section class="mail-readiness" role="status" aria-labelledby="mail-readiness-title">
                <h2 id="mail-readiness-title"><?= $mailboxReadiness['status'] === 'partial' ? 'Inbox partially available' : 'Inbox needs setup' ?></h2>
                <p><?= e((string) ($mailboxReadiness['reason'] ?? 'Configure at least one deployment mailbox to enable reader correspondence.')) ?></p>
                <p class="quiet">Healthy account caches remain available. Inbox credentials are deployment-only and are never shown here.</p>
            </section>
        <?php endif; ?>

        <?php if ($area === 'inbox'): ?>
            <section aria-labelledby="mail-inbox">
                <h2 id="mail-inbox"><?= $selectedAccount === 'all' ? 'All accounts' : e($selectedLabel) ?></h2>
                <?php if ($mailboxReadiness['status'] === 'disabled'): ?>
                    <p class="quiet">No mailbox account is enabled.</p>
                <?php elseif ($messages === []): ?>
                    <p class="quiet">No reader messages<?= $selectedAccount === 'all' ? '' : ' in this account' ?>.</p>
                <?php else: ?>
                    <ol class="mail-item-list">
                        <?php foreach ($messages as $message): ?>
                            <li><a href="/mail/messages/<?= rawurlencode($message->sourceAccountId) ?>/<?= rawurlencode($message->sourceMessageId) ?>"><strong><?= e($message->subject) ?></strong><span><?= e($message->from) ?> · <?= e($message->sourceLabel) ?><?= $message->unread ? ' · Unread' : '' ?></span><time datetime="<?= e($message->receivedAt->format(DATE_ATOM)) ?>"><?= e($message->receivedAt->format('M j, H:i')) ?></time></a></li>
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
                            <li><a href="/mail?area=inbox&amp;draft=<?= rawurlencode($draft->id) ?>"<?= $selectedDraft?->id === $draft->id ? ' aria-current="page"' : '' ?>><strong><?= e($draft->subject !== '' ? $draft->subject : 'Untitled draft') ?></strong><span><?= e($draft->to !== '' ? $draft->to : 'No recipient') ?></span><time datetime="<?= e($draft->updatedAt->format(DATE_ATOM)) ?>"><?= e($draft->updatedAt->format('M j, H:i')) ?></time></a></li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <?php if (!$newsletterReady): ?>
                <section class="mail-readiness" role="status"><h2>Newsletter needs setup</h2><p>Configure NEWSLETTER_SECRET or APP_KEY before subscriptions and campaign dispatch are available.</p></section>
            <?php endif; ?>
            <section id="campaign-drafts" aria-labelledby="campaign-drafts-title">
                <h2 id="campaign-drafts-title">Draft campaigns</h2>
                <?php if ($campaignDrafts === []): ?><p class="quiet">No campaign drafts yet.</p><?php else: ?>
                    <ol class="mail-item-list">
                        <?php foreach ($campaignDrafts as $draft): ?>
                            <li><a href="/mail/campaign-drafts/<?= rawurlencode($draft->id) ?>"><strong><?= e($draft->subject !== '' ? $draft->subject : 'Untitled campaign') ?></strong><span><?= e($draft->sourceType === 'post' ? 'From post ' . (string) $draft->sourceId : 'Campaign draft') ?></span><time datetime="<?= e($draft->updatedAt->format(DATE_ATOM)) ?>"><?= e($draft->updatedAt->format('M j, H:i')) ?></time></a></li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>
            <section aria-labelledby="mail-review-queue"><h2 id="mail-review-queue">Newsletter review</h2>
                <?php if ($queue === []): ?><p class="quiet">No newsletter candidates.</p><?php else: ?>
                    <ol class="mail-item-list"><?php foreach ($queue as $candidate): ?><li><a href="/mail?area=campaigns&amp;post=<?= rawurlencode($candidate['slug']) ?>"><strong><?= e($candidate['title']) ?></strong><span><?= e((string) ($candidate['author'] ?? '—')) ?></span><time datetime="<?= e($candidate['published_at']) ?>"><?= e((new DateTimeImmutable($candidate['published_at']))->format('M j, Y')) ?></time></a></li><?php endforeach; ?></ol>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </section>

    <section class="mail-detail-panel" aria-labelledby="mail-detail-title">
        <?php if ($area === 'campaigns'): ?>
            <header class="mail-panel-header"><p class="eyebrow">Newsletter</p><h2 id="mail-detail-title">Campaign detail</h2></header>
            <section><h3>Audience now</h3><p><strong><?= $audience['count'] ?></strong> confirmed <?= $audience['count'] === 1 ? 'recipient' : 'recipients' ?></p><p class="quiet">This count is informational only. The recipient set is snapshotted when a reviewed campaign is confirmed and queued.</p></section>
            <section><h3>Selected candidate</h3>
                <?php if ($campaign === null): ?><p class="quiet">Select a campaign draft or newsletter candidate from the center list.</p><?php else: ?>
                    <article><h3><?= e($campaign['post']['title']) ?></h3><?php if ($campaign['post']['excerpt']): ?><p><?= e($campaign['post']['excerpt']) ?></p><?php endif; ?><div class="form-actions"><a href="<?= e($campaign['post']['url']) ?>">View post</a><?php if ($newsletterReady): ?><a class="button" href="/mail/confirm?post=<?= rawurlencode($campaign['post']['slug']) ?>">Review dispatch proof</a><?php endif; ?></div></article>
                <?php endif; ?>
            </section>
        <?php elseif ($selectedDraft !== null): ?>
            <header class="mail-panel-header"><p class="eyebrow">Correspondence</p><h2 id="mail-detail-title">Compose mail</h2><p class="quiet">Stored privately. Sending does not alter posts or campaigns.</p></header>
            <?php if ($composeError !== ''): ?><p class="mail-compose-error" role="alert"><?= e($composeError) ?></p><?php endif; ?>
            <form class="mail-compose-form mail-compose-paper" method="post" action="/mail/drafts/<?= rawurlencode($selectedDraft->id) ?>">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <div class="mail-compose-field"><label for="mail-to">To</label><input id="mail-to" type="email" name="to" required value="<?= e($selectedDraft->to) ?>"></div>
                <div class="mail-compose-field"><label for="mail-subject">Subject</label><input id="mail-subject" name="subject" required value="<?= e($selectedDraft->subject) ?>"></div>
                <div class="mail-compose-body"><label for="mail-text">Message</label><textarea id="mail-text" name="text" rows="18" required><?= e($selectedDraft->text) ?></textarea></div>
                <div class="form-actions"><button type="submit" name="intent" value="save">Save draft</button><button type="submit" name="intent" value="send">Send mail</button></div>
            </form>
        <?php else: ?>
            <header class="mail-panel-header"><p class="eyebrow">Reader mail</p><h2 id="mail-detail-title">Message detail</h2></header>
            <p class="quiet">Select a message or draft from the center list.</p>
            <div class="form-actions"><a class="button" href="/mail/compose">Compose mail</a></div>
        <?php endif; ?>

        <form class="form-actions" method="post" action="/logout"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button type="submit">Sign out</button></form>
    </section>
</main>
</body>
</html>

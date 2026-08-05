<?php
/** @var array<string, mixed> $user */
/** @var string $siteName */
/** @var 'inbox'|'campaigns' $area */
/** @var array{reader:int,campaigns:int,total:int,detail:string} $attention */
/** @var array<string,mixed> $mailboxReadiness */
/** @var list<\Katakata\Email\MessageSummary> $messages */
/** @var list<\Katakata\Email\Draft> $drafts */
/** @var list<\Katakata\Mail\CampaignDraft> $campaignDrafts */
/** @var list<array{slug:string,title:string,published_at:string,author:?string,excerpt:?string,url:string}> $queue */
/** @var array{count:int,recipients:list<array{email:string,confirmed_at:?string}>} $audience */
/** @var array{post:array{slug:string,title:string,published_at:string,author:?string,excerpt:?string,url:string},recipient_count:int}|null $campaign */
/** @var bool $newsletterReady */
/** @var bool $refreshRequested */
/** @var string $csrf */

$accountStates = array_values(array_filter((array) ($mailboxReadiness['accounts'] ?? []), static fn (mixed $state): bool => is_array($state) && isset($state['account_id'], $state['label'])));
$selectedAccount = trim((string) ($_GET['account'] ?? 'all'));
$selectedMessageAccount = trim((string) ($_GET['message_account'] ?? ''));
$selectedMessageId = trim((string) ($_GET['message'] ?? ''));
$knownAccounts = array_column($accountStates, 'account_id');
if ($selectedAccount !== 'all' && !in_array($selectedAccount, $knownAccounts, true)) $selectedAccount = 'all';
if ($selectedMessageAccount !== '' && !in_array($selectedMessageAccount, $knownAccounts, true)) { $selectedMessageAccount = ''; $selectedMessageId = ''; }

$selectedMessageRecord = null;
foreach ($messages as $message) {
    if ($message->sourceAccountId === $selectedMessageAccount && $message->sourceMessageId === $selectedMessageId) { $selectedMessageRecord = $message; break; }
}
if ($selectedAccount !== 'all') $messages = array_values(array_filter($messages, static fn (\Katakata\Email\MessageSummary $message): bool => $message->sourceAccountId === $selectedAccount));
$inboxQuery = static fn (string $account): string => '/mail?area=inbox&amp;account=' . rawurlencode($account);
$selectedLabel = 'Inbox';
foreach ($accountStates as $accountState) if ((string) $accountState['account_id'] === $selectedAccount) { $selectedLabel = (string) $accountState['label']; break; }
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Mail — <?= e($siteName) ?></title><link rel="stylesheet" href="/assets/css/site.css"><link rel="stylesheet" href="/assets/css/mail.css"></head>
<body class="dashboard-page mail-page">
<header class="dashboard-header"><a class="site-name" href="/dashboard"><?= e($siteName) ?></a><nav aria-label="Mail actions"><a class="button" href="/mail/compose">Compose</a><a href="/dashboard/settings">Settings</a></nav></header>
<main class="mail-workspace-shell">
<aside class="mail-sidebar" aria-label="Mail destinations">
<section><p class="eyebrow">Mail</p><a href="/mail?area=inbox"<?= $area === 'inbox' && $selectedAccount === 'all' ? ' aria-current="page"' : '' ?>>Inbox<?= $attention['reader'] > 0 ? ' (' . $attention['reader'] . ')' : '' ?></a>
<?php if ($accountStates !== []): ?><nav class="mail-account-nav" aria-label="Inbox accounts"><a href="<?= $inboxQuery('all') ?>"<?= $selectedAccount === 'all' ? ' aria-current="page"' : '' ?>>All accounts</a><?php foreach ($accountStates as $accountState): ?><?php $accountStatus = (string) ($accountState['status'] ?? 'needs_setup'); ?><a href="<?= $inboxQuery((string) $accountState['account_id']) ?>"<?= $selectedAccount === $accountState['account_id'] ? ' aria-current="page"' : '' ?>><?= e((string) $accountState['label']) ?><?php if ($accountStatus !== 'ready'): ?><span class="quiet"> · <?= $accountStatus === 'error' ? 'Needs attention' : 'Needs setup' ?></span><?php endif; ?></a><?php endforeach; ?></nav><?php endif; ?>
<a href="/mail?area=inbox#mail-drafts">Draft replies</a><a href="/mail/sent">Sent mail</a><a href="/mail/archive">Archive</a></section>
<section><p class="eyebrow">Newsletter</p><a href="/mail?area=campaigns"<?= $area === 'campaigns' ? ' aria-current="page"' : '' ?>>Campaigns<?= $attention['campaigns'] > 0 ? ' (' . $attention['campaigns'] . ')' : '' ?></a><a href="/mail?area=campaigns#campaign-drafts">Draft campaigns</a><a href="/mail/campaigns">Sent campaigns</a></section>
<section><a href="/dashboard/settings">Settings</a></section>
</aside>
<section class="mail-list-panel" aria-labelledby="mail-list-title">
<header class="mail-panel-header"><div class="mail-panel-header-title-row"><h1 id="mail-list-title"><?= $area === 'inbox' ? e($selectedAccount === 'all' ? 'Inbox' : $selectedLabel) : 'Campaigns' ?></h1><?php if ($area === 'inbox'): ?><form method="post" action="/mail/refresh"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button class="mail-refresh-button" type="submit">Get new mail</button></form><?php endif; ?></div><p><?= e($attention['detail']) ?></p></header>
<?php if ($refreshRequested): ?><p class="mail-refresh-notice" role="status">Refresh requested. New mail appears after the server’s next scheduled check.</p><?php endif; ?>
<?php if (!in_array($mailboxReadiness['status'], ['ready', 'disabled'], true)): ?><section class="mail-readiness" role="status" aria-labelledby="mail-readiness-title"><h2 id="mail-readiness-title"><?= $mailboxReadiness['status'] === 'partial' ? 'Inbox partially available' : 'Inbox needs setup' ?></h2><p><?= e((string) ($mailboxReadiness['reason'] ?? 'Configure at least one deployment mailbox to enable reader correspondence.')) ?></p><p class="quiet">Healthy account caches remain available. Inbox credentials are deployment-only and are never shown here.</p></section><?php endif; ?>
<?php if ($area === 'inbox'): ?>
<section aria-label="Messages"><?php if ($mailboxReadiness['status'] === 'disabled'): ?><p class="quiet">No mailbox account is enabled.</p><?php elseif ($messages === []): ?><p class="quiet">No reader messages<?= $selectedAccount === 'all' ? '' : ' in this account' ?>.</p><?php else: ?><ol class="mail-item-list mail-message-list"><?php foreach ($messages as $message): ?><?php
$messageHref = '/mail?area=inbox&account=' . rawurlencode($selectedAccount) . '&message_account=' . rawurlencode((string) $message->sourceAccountId) . '&message=' . rawurlencode((string) $message->sourceMessageId);
$detailUrl = '/mail/messages/' . rawurlencode((string) $message->sourceAccountId) . '/' . rawurlencode((string) $message->sourceMessageId) . '?fragment=1';
$isSelected = $selectedMessageAccount === $message->sourceAccountId && $selectedMessageId === $message->sourceMessageId;
$snippet = trim(preg_replace('/\s+/', ' ', $message->text) ?? '');
?><li class="<?= $message->unread ? 'is-unread' : 'is-read' ?>"><a href="<?= e($messageHref) ?>" data-mail-message-link data-detail-url="<?= e($detailUrl) ?>" data-account="<?= e((string) $message->sourceAccountId) ?>" data-message="<?= e((string) $message->sourceMessageId) ?>"<?= $isSelected ? ' aria-current="page"' : '' ?>><span class="mail-item-topline"><span class="mail-item-sender"><?= e($message->from) ?></span><time datetime="<?= e($message->receivedAt->format(DATE_ATOM)) ?>"><?= e($message->receivedAt->format('M j, H:i')) ?></time></span><strong><?= e($message->subject) ?></strong><span class="mail-item-source"><?= e((string) $message->sourceLabel) ?><?= $message->unread ? ' · Unread' : '' ?></span><?php if ($snippet !== ''): ?><span class="mail-item-snippet"><?= e($snippet) ?></span><?php endif; ?></a></li><?php endforeach; ?></ol><?php endif; ?></section>
<section id="mail-drafts" aria-labelledby="mail-drafts-title"><h2 id="mail-drafts-title">Draft replies</h2><?php if ($drafts === []): ?><p class="quiet">No saved correspondence drafts.</p><?php else: ?><ol class="mail-item-list"><?php foreach ($drafts as $draft): ?><li><a href="/mail/drafts/<?= rawurlencode($draft->id) ?>/edit"><strong><?= e($draft->subject !== '' ? $draft->subject : 'Untitled draft') ?></strong><span><?= e($draft->to !== '' ? $draft->to : 'No recipient') ?></span><time datetime="<?= e($draft->updatedAt->format(DATE_ATOM)) ?>"><?= e($draft->updatedAt->format('M j, H:i')) ?></time></a></li><?php endforeach; ?></ol><?php endif; ?></section>
<?php else: ?>
<?php if (!$newsletterReady): ?><section class="mail-readiness" role="status"><h2>Newsletter needs setup</h2><p>Configure NEWSLETTER_SECRET or APP_KEY before subscriptions and campaign dispatch are available.</p></section><?php endif; ?>
<section id="campaign-drafts" aria-labelledby="campaign-drafts-title"><h2 id="campaign-drafts-title">Draft campaigns</h2><?php if ($campaignDrafts === []): ?><p class="quiet">No campaign drafts yet.</p><?php else: ?><ol class="mail-item-list campaign-item-list"><?php foreach ($campaignDrafts as $draft): ?><li><a href="/mail/campaign-drafts/<?= rawurlencode($draft->id) ?>"><strong><?= e($draft->subject !== '' ? $draft->subject : 'Untitled campaign') ?></strong><span><?= e($draft->sourceType === 'post' ? 'From post ' . (string) $draft->sourceId : 'Campaign draft') ?> · <span class="mail-status-pill">Draft</span></span><time datetime="<?= e($draft->updatedAt->format(DATE_ATOM)) ?>"><?= e($draft->updatedAt->format('M j, H:i')) ?></time></a></li><?php endforeach; ?></ol><?php endif; ?></section>
<section aria-labelledby="mail-review-queue"><h2 id="mail-review-queue">Newsletter review</h2><?php if ($queue === []): ?><p class="quiet">No newsletter candidates.</p><?php else: ?><ol class="mail-item-list campaign-item-list"><?php foreach ($queue as $candidate): ?><li><a href="/mail?area=campaigns&amp;post=<?= rawurlencode($candidate['slug']) ?>"><strong><?= e($candidate['title']) ?></strong><span><?= e((string) ($candidate['author'] ?? '—')) ?> · <span class="mail-status-pill is-ready">Ready for review</span></span><time datetime="<?= e($candidate['published_at']) ?>"><?= e((new DateTimeImmutable($candidate['published_at']))->format('M j, Y')) ?></time></a></li><?php endforeach; ?></ol><?php endif; ?></section>
<?php endif; ?>
</section>
<section class="mail-detail-panel" aria-live="polite">
<?php if ($area === 'campaigns'): ?><?php if ($campaign === null): ?><p class="quiet">Select a campaign.</p><?php else: ?><header class="mail-panel-header"><h2 id="mail-detail-title"><?= e($campaign['post']['title']) ?></h2></header><section><h3>Audience now</h3><p><strong><?= $audience['count'] ?></strong> confirmed <?= $audience['count'] === 1 ? 'recipient' : 'recipients' ?></p><p class="quiet">This count is informational only. The recipient set is snapshotted when a reviewed campaign is confirmed and queued.</p></section><section><article><?php if ($campaign['post']['excerpt']): ?><p><?= e($campaign['post']['excerpt']) ?></p><?php endif; ?><div class="form-actions"><a href="<?= e($campaign['post']['url']) ?>">View post</a><?php if ($newsletterReady): ?><a class="button" href="/mail/confirm?post=<?= rawurlencode($campaign['post']['slug']) ?>">Review dispatch proof</a><?php endif; ?></div></article></section><?php endif; ?>
<?php else: ?><div data-mail-reader><?php if ($selectedMessageRecord === null): ?><p class="quiet">Select a message.</p><?php else: ?><?php $messagePath = '/mail/messages/' . rawurlencode((string) $selectedMessageRecord->sourceAccountId) . '/' . rawurlencode((string) $selectedMessageRecord->sourceMessageId); ?><article class="mail-message mail-message-panel"><header><p class="eyebrow"><?= e((string) $selectedMessageRecord->sourceLabel) ?> · <?= $selectedMessageRecord->unread ? 'Unread' : 'Read' ?></p><h2 tabindex="-1"><?= e($selectedMessageRecord->subject) ?></h2><p class="quiet">From <?= e($selectedMessageRecord->from) ?> · <?= e($selectedMessageRecord->receivedAt->format('M j, Y H:i')) ?> UTC</p></header><div class="mail-message-body"><?= nl2br(e($selectedMessageRecord->text)) ?></div><div class="form-actions"><form method="post" action="<?= e($messagePath) ?>/reply"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button type="submit">Reply</button></form><form method="post" action="<?= e($messagePath) ?>/<?= $selectedMessageRecord->unread ? 'read' : 'unread' ?>"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button type="submit">Mark <?= $selectedMessageRecord->unread ? 'read' : 'unread' ?></button></form><form method="post" action="<?= e($messagePath) ?>/archive"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button type="submit">Archive</button></form><form method="post" action="<?= e($messagePath) ?>/delete"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button type="submit">Delete cached copy</button></form></div></article><?php endif; ?></div><?php endif; ?>
<form class="form-actions" method="post" action="/logout"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button type="submit">Sign out</button></form>
</section>
</main><script src="/assets/js/mail-reader.js" defer></script></body></html>

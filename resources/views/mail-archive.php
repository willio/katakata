<?php
/** @var array<string, mixed> $user */
/** @var string $siteName */
/** @var list<\Katakata\Email\MessageSummary> $messages */
/** @var array{status:string,reason:?string,last_synced_at:?string} $mailboxReadiness */
/** @var string $csrf */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mail archive — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="/assets/css/site.css">
    <link rel="stylesheet" href="/assets/css/boundary.css">
    <link rel="stylesheet" href="/assets/css/mail.css">
</head>
<body class="dashboard-page mail-page<?= ($buttonStyle ?? 'regular') === 'pill' ? ' buttons-pill' : '' ?>">
<header class="dashboard-header">
    <a class="site-name" href="/dashboard"><?= e($siteName) ?></a>
    <nav aria-label="Mail actions">
        <a href="/mail">Inbox</a>
        <a class="button" href="/mail/compose">Compose</a>
        <a href="/dashboard/settings">Settings</a>
    </nav>
</header>
<main class="mail-workspace-shell">
    <aside class="mail-sidebar" aria-label="Mail destinations">
        <section>
            <p class="eyebrow">Mail</p>
            <a href="/mail?area=inbox">Inbox</a>
            <a href="/mail?area=inbox#mail-drafts">Draft replies</a>
            <a href="/mail/archive" aria-current="page">Archive</a>
        </section>
        <section>
            <p class="eyebrow">Newsletter</p>
            <a href="/mail?area=campaigns">Campaigns</a>
            <a href="/mail?area=campaigns#campaign-drafts">Draft campaigns</a>
            <a href="/mail/campaigns">Sent campaigns</a>
        </section>
    </aside>

    <section class="mail-list-panel" aria-labelledby="mail-archive-title">
        <header class="mail-panel-header">
            <p class="eyebrow">Editorial correspondence</p>
            <h1 id="mail-archive-title">Archive</h1>
            <p>Reader messages removed from the active inbox.</p>
        </header>

        <?php if ($mailboxReadiness['status'] !== 'ready'): ?>
            <section class="mail-readiness" role="status">
                <h2>Mailbox needs attention</h2>
                <p><?= e((string) ($mailboxReadiness['reason'] ?? 'The private mailbox cache is unavailable.')) ?></p>
            </section>
        <?php elseif ($messages === []): ?>
            <p class="quiet">No archived reader messages.</p>
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

    <section class="mail-detail-panel" aria-labelledby="mail-archive-detail-title">
        <header class="mail-panel-header">
            <p class="eyebrow">Reader mail</p>
            <h2 id="mail-archive-detail-title">Archived message</h2>
        </header>
        <p class="quiet">Select a message from the archive to inspect it.</p>
        <form class="form-actions" method="post" action="/logout">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <button type="submit">Sign out</button>
        </form>
    </section>
</main>
</body>
</html>

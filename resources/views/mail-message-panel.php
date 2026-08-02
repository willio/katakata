<?php
/** @var \Katakata\Email\Message $message */
/** @var string $csrf */
$messagePath = '/mail/messages/' . rawurlencode((string) $message->sourceAccountId) . '/' . rawurlencode((string) $message->sourceMessageId);
?>
<article class="mail-message" data-mail-message-panel>
    <header>
        <p class="eyebrow"><?= e((string) $message->sourceLabel) ?> · <?= $message->unread ? 'Unread' : 'Read' ?></p>
        <h2 id="mail-detail-title"><?= e($message->subject) ?></h2>
        <p class="quiet">From <?= e($message->from) ?> · <?= e($message->receivedAt->format('M j, Y H:i')) ?> UTC</p>
    </header>
    <div class="mail-message-body"><?= nl2br(e($message->text)) ?></div>

    <section class="mail-readiness" aria-labelledby="message-attachments">
        <h3 id="message-attachments">Attachments</h3>
        <p>Attachment files are not copied into Katakata. Open the original <?= e((string) $message->sourceLabel) ?> account in its mailbox application to review or download them.</p>
    </section>

    <div class="form-actions">
        <form method="post" action="<?= e($messagePath) ?>/reply">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <button type="submit">Reply</button>
        </form>
        <form method="post" action="<?= e($messagePath) ?>/<?= $message->unread ? 'read' : 'unread' ?>">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <button type="submit">Mark <?= $message->unread ? 'read' : 'unread' ?></button>
        </form>
        <form method="post" action="<?= e($messagePath) ?>/archive">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <button type="submit">Archive</button>
        </form>
        <form method="post" action="<?= e($messagePath) ?>/delete">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <button type="submit">Delete cached copy</button>
        </form>
    </div>
    <p class="quiet">Deleting removes only Katakata’s private cached copy. It does not change the original mailbox.</p>
</article>

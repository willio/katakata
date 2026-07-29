<?php
/** @var string $mode */
/** @var string|null $message */
/** @var string|null $error */
/** @var string|null $token */
/** @var string|null $csrf */
/** @var string $siteName */
$title = match ($mode) {
    'pending' => 'Check your email',
    'confirmed' => 'Subscription confirmed',
    'unsubscribe' => 'Unsubscribe',
    'unsubscribed' => 'Unsubscribed',
    default => 'Newsletter',
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body>
<header class="site-header">
    <a class="site-name" href="/"><?= e($siteName) ?></a>
    <nav aria-label="Primary"><a href="/archive">Archive</a></nav>
</header>
<main class="auth-shell">
    <article>
        <p class="eyebrow">Newsletter</p>
        <h1><?= e($title) ?></h1>
        <?php if ($message !== null): ?><p><?= e($message) ?></p><?php endif; ?>
        <?php if ($error !== null): ?><p class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?>

        <?php if ($mode === 'subscribe'): ?>
            <form method="post" action="/newsletter/subscribe">
                <input type="hidden" name="csrf" value="<?= e((string) $csrf) ?>">
                <div class="field">
                    <label for="newsletter-email">Email</label>
                    <div class="field-control">
                        <input id="newsletter-email" name="email" type="email" autocomplete="email" placeholder="Email" aria-describedby="newsletter-privacy" required>
                        <button class="field-clear" type="button" data-field-clear="newsletter-email" aria-label="Clear email" hidden>
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
                <p id="newsletter-privacy" class="quiet">One confirmation email. Unsubscribe whenever you like.</p>
                <div class="form-actions"><button class="button" type="submit">Subscribe</button></div>
            </form>
        <?php elseif ($mode === 'unsubscribe' && $error === null): ?>
            <p>This immediately stops future newsletter delivery.</p>
            <form method="post" action="/newsletter/unsubscribe">
                <input type="hidden" name="csrf" value="<?= e((string) $csrf) ?>">
                <input type="hidden" name="token" value="<?= e((string) $token) ?>">
                <div class="form-actions"><button class="button" type="submit">Unsubscribe</button></div>
            </form>
        <?php endif; ?>

        <p><a href="/">Return home</a></p>
    </article>
</main>
<script src="/assets/js/fields.js" defer></script>
</body>
</html>

<?php
/** @var string $mode */
/** @var string|null $error */
/** @var string|null $token */
/** @var string|null $csrf */
$title = $mode === 'login' ? 'Sign in' : 'Accept invitation';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> — Katakata</title>
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body>
<main class="site-shell auth-shell">
    <article>
        <p class="eyebrow">Katakata editorial</p>
        <h1><?= e($title) ?></h1>
        <?php if ($error !== null): ?><p role="alert"><?= e($error) ?></p><?php endif; ?>
        <form method="post" action="<?= $mode === 'login' ? '/login' : '/register' ?>">
            <?php if ($csrf !== null): ?><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><?php endif; ?>
            <?php if ($token !== null): ?><input type="hidden" name="token" value="<?= e($token) ?>"><?php endif; ?>
            <label>Email <input name="email" type="email" autocomplete="email" required></label>
            <label>Password <input name="password" type="password" autocomplete="<?= $mode === 'login' ? 'current-password' : 'new-password' ?>" minlength="12" required></label>
            <button type="submit"><?= e($title) ?></button>
        </form>
        <?php if ($mode === 'login'): ?>
            <hr>
            <form data-passkey-login>
                <input type="hidden" name="csrf" value="<?= e((string) $csrf) ?>">
                <label>Email <input name="email" type="email" autocomplete="username webauthn" required></label>
                <button type="submit">Sign in with a passkey</button>
                <p data-passkey-status aria-live="polite"></p>
            </form>
        <?php endif; ?>
    </article>
</main>
<script src="/assets/js/passkeys.js" defer></script>
</body>
</html>

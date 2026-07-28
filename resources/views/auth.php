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
        <?php if ($error !== null): ?><p class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <form method="post" action="<?= $mode === 'login' ? '/login' : '/register' ?>">
            <?php if ($csrf !== null): ?><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><?php endif; ?>
            <?php if ($token !== null): ?><input type="hidden" name="token" value="<?= e($token) ?>"><?php endif; ?>
            <div class="field">
                <label for="auth-email">Email</label>
                <div class="field-control">
                    <input id="auth-email" name="email" type="email" autocomplete="email" placeholder="Email" required>
                    <button class="field-clear" type="button" data-field-clear="auth-email" aria-label="Clear email" hidden>
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
            <div class="field">
                <label for="auth-password">Password</label>
                <div class="field-control">
                    <input id="auth-password" name="password" type="password" autocomplete="<?= $mode === 'login' ? 'current-password' : 'new-password' ?>" minlength="12" placeholder="Password" required>
                    <button class="field-clear" type="button" data-field-clear="auth-password" aria-label="Clear password" hidden>
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
            <div class="form-actions"><button class="button" type="submit"><?= e($title) ?></button></div>
        </form>
        <?php if ($mode === 'login'): ?>
            <hr>
            <form data-passkey-login>
                <input type="hidden" name="csrf" value="<?= e((string) $csrf) ?>">
                <div class="field">
                    <label for="passkey-email">Email for passkey sign-in</label>
                    <div class="field-control">
                        <input id="passkey-email" name="email" type="email" autocomplete="username webauthn" placeholder="Email for passkey sign-in" required>
                        <button class="field-clear" type="button" data-field-clear="passkey-email" aria-label="Clear passkey email" hidden>
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
                <div class="form-actions"><button class="button" type="submit">Sign in with a passkey</button></div>
                <p class="quiet" data-passkey-status aria-live="polite"></p>
            </form>
        <?php endif; ?>
    </article>
</main>
<script src="/assets/js/fields.js" defer></script>
<script src="/assets/js/passkeys.js" defer></script>
</body>
</html>

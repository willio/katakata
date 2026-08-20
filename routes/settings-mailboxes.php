<?php

declare(strict_types=1);

use Katakata\Auth\Session;
use Katakata\Email\MailboxAccount;
use Katakata\Email\MailboxAccountStore;
use Katakata\Email\MailboxCredentialResolver;
use Katakata\Email\Providers\AccountCachedMailboxProvider;
use Katakata\Email\Providers\CachedMailboxProvider;
use Katakata\Editorial\AtomicFile;
use Katakata\Http\Request;
use Katakata\Http\Response;
use Katakata\View;

/**
 * @var \Katakata\Http\Router $router
 * @var \Katakata\Application $app
 */

$authorizeMailboxSettings = static function () use ($app): array|Response {
    $session = $app->make(Session::class);
    $user = $session->user();
    if ($user === null) {
        return Response::redirect('/login', 302);
    }
    if (!$session->canManageSettings()) {
        return Response::html('Forbidden.', 403);
    }
    return $user;
};

$validMailboxSettingsCsrf = static function (Request $request) use ($app): bool {
    return $app->make(Session::class)->validCsrf($request->body['csrf'] ?? null);
};

$mailboxAccountFromRequest = static function (Request $request, ?string $id = null): MailboxAccount {
    return new MailboxAccount(
        id: $id ?? strtolower(trim((string) ($request->body['id'] ?? ''))),
        label: trim((string) ($request->body['label'] ?? '')),
        host: trim((string) ($request->body['host'] ?? '')),
        port: (int) ($request->body['port'] ?? 993),
        encryption: 'ssl',
        mailbox: trim((string) ($request->body['mailbox'] ?? 'INBOX')) ?: 'INBOX',
        usernameSecret: trim((string) ($request->body['username_secret'] ?? '')),
        passwordSecret: trim((string) ($request->body['password_secret'] ?? '')),
        enabled: isset($request->body['enabled']) && (string) $request->body['enabled'] === '1',
    );
};

$settingsRedirect = static function (?string $error = null): Response {
    $query = $error === null ? 'saved=1' : 'error=' . rawurlencode($error);
    return Response::redirect('/dashboard/settings/mailboxes?' . $query, 303);
};

$router->get('/dashboard/settings/mailboxes', function (Request $request) use ($app, $authorizeMailboxSettings): Response {
    $authorization = $authorizeMailboxSettings();
    if ($authorization instanceof Response) {
        return $authorization;
    }

    $credentials = $app->make(MailboxCredentialResolver::class);
    $accounts = [];
    foreach ($app->make(MailboxAccountStore::class)->all() as $account) {
        $readiness = (new AccountCachedMailboxProvider(
            $account,
            new CachedMailboxProvider(
                $app->storagePath('mail/cache/' . $account->id),
                $app->make(AtomicFile::class),
            ),
        ))->readiness();
        $accounts[] = [
            'account' => $account,
            'missing' => $credentials->missing($account),
            'readiness' => $readiness,
        ];
    }

    return Response::html($app->make(View::class)->render('dashboard-settings-mailboxes', [
        'user' => $authorization,
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'accounts' => $accounts,
        'saved' => ($request->query['saved'] ?? '') === '1',
        'error' => $request->query['error'] ?? null,
        'csrf' => $app->make(Session::class)->csrf(),
        'limit' => MailboxAccountStore::MAX_ACCOUNTS,
        'buttonStyle' => (string) ($app->make(\Katakata\Dashboard\DashboardSettings::class)->section('appearance')['button_style'] ?? 'regular'),
    ]));
});

$router->post('/dashboard/settings/mailboxes', function (Request $request) use (
    $app,
    $authorizeMailboxSettings,
    $validMailboxSettingsCsrf,
    $mailboxAccountFromRequest,
    $settingsRedirect,
): Response {
    $authorization = $authorizeMailboxSettings();
    if ($authorization instanceof Response) {
        return $authorization;
    }
    if (!$validMailboxSettingsCsrf($request)) {
        return Response::html('Invalid CSRF token.', 419);
    }
    try {
        $app->make(MailboxAccountStore::class)->create($mailboxAccountFromRequest($request));
        return $settingsRedirect();
    } catch (\Throwable $error) {
        return $settingsRedirect($error->getMessage());
    }
});

$router->post('/dashboard/settings/mailboxes/{id}', function (Request $request, string $id) use (
    $app,
    $authorizeMailboxSettings,
    $validMailboxSettingsCsrf,
    $settingsRedirect,
): Response {
    $authorization = $authorizeMailboxSettings();
    if ($authorization instanceof Response) {
        return $authorization;
    }
    if (!$validMailboxSettingsCsrf($request)) {
        return Response::html('Invalid CSRF token.', 419);
    }
    try {
        $store = $app->make(MailboxAccountStore::class);
        $existing = $store->find($id);
        if ($existing === null) {
            return Response::notFound();
        }
        $store->update(new MailboxAccount(
            id: $existing->id,
            label: trim((string) ($request->body['label'] ?? '')),
            host: $existing->host,
            port: $existing->port,
            encryption: $existing->encryption,
            mailbox: $existing->mailbox,
            usernameSecret: $existing->usernameSecret,
            passwordSecret: $existing->passwordSecret,
            enabled: $existing->enabled,
        ));
        return $settingsRedirect();
    } catch (\Throwable $error) {
        return $settingsRedirect($error->getMessage());
    }
});

foreach (['enable' => true, 'disable' => false] as $action => $enabled) {
    $router->post('/dashboard/settings/mailboxes/{id}/' . $action, function (Request $request, string $id) use (
        $app,
        $authorizeMailboxSettings,
        $validMailboxSettingsCsrf,
        $settingsRedirect,
        $enabled,
    ): Response {
        $authorization = $authorizeMailboxSettings();
        if ($authorization instanceof Response) {
            return $authorization;
        }
        if (!$validMailboxSettingsCsrf($request)) {
            return Response::html('Invalid CSRF token.', 419);
        }
        $store = $app->make(MailboxAccountStore::class);
        $account = $store->find($id);
        if ($account === null) {
            return Response::notFound();
        }
        $store->update(new MailboxAccount(
            id: $account->id,
            label: $account->label,
            host: $account->host,
            port: $account->port,
            encryption: $account->encryption,
            mailbox: $account->mailbox,
            usernameSecret: $account->usernameSecret,
            passwordSecret: $account->passwordSecret,
            enabled: $enabled,
        ));
        return $settingsRedirect();
    });
}

$router->post('/dashboard/settings/mailboxes/{id}/delete', function (Request $request, string $id) use (
    $app,
    $authorizeMailboxSettings,
    $validMailboxSettingsCsrf,
    $settingsRedirect,
): Response {
    $authorization = $authorizeMailboxSettings();
    if ($authorization instanceof Response) {
        return $authorization;
    }
    if (!$validMailboxSettingsCsrf($request)) {
        return Response::html('Invalid CSRF token.', 419);
    }
    if (($request->body['confirm'] ?? '') !== $id) {
        return $settingsRedirect('Type the mailbox account ID to confirm deletion.');
    }
    $store = $app->make(MailboxAccountStore::class);
    if ($store->find($id) === null) {
        return Response::notFound();
    }
    $store->delete($id);
    if (($request->body['purge_cache'] ?? '') === '1') {
        $removeTree = static function (string $path) use (&$removeTree): void {
            if (!is_dir($path)) {
                return;
            }
            foreach (scandir($path) ?: [] as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $target = $path . DIRECTORY_SEPARATOR . $item;
                is_dir($target) ? $removeTree($target) : @unlink($target);
            }
            @rmdir($path);
        };
        $removeTree($app->storagePath('mail/cache/' . $id));
    }
    return $settingsRedirect();
});

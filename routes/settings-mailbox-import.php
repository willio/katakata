<?php

declare(strict_types=1);

use Katakata\Auth\Session;
use Katakata\Email\Import\MailProfileImportStore;
use Katakata\Email\Import\MobileconfigAccountImporter;
use Katakata\Email\MailboxAccount;
use Katakata\Email\MailboxAccountStore;
use Katakata\Http\Request;
use Katakata\Http\Response;
use Katakata\View;

/** @var \Katakata\Http\Router $router */
/** @var \Katakata\Application $app */

$authorizeMailboxImport = static function () use ($app): array|Response {
    $session = $app->make(Session::class);
    $user = $session->user();
    if ($user === null) {
        return Response::redirect('/login', 302);
    }
    return $session->canManageSettings() ? $user : Response::html('Forbidden.', 403);
};

$renderMailboxImport = static function (array $user, array $candidates = [], ?string $token = null, ?string $error = null) use ($app): Response {
    return Response::html($app->make(View::class)->render('dashboard-settings-mailbox-import', [
        'user' => $user,
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'candidates' => $candidates,
        'token' => $token,
        'error' => $error,
        'csrf' => $app->make(Session::class)->csrf(),
    ]), $error === null ? 200 : 422);
};

$router->get('/dashboard/settings/mailboxes/import', function (Request $request) use ($authorizeMailboxImport, $renderMailboxImport): Response {
    $user = $authorizeMailboxImport();
    return $user instanceof Response ? $user : $renderMailboxImport($user);
});

$router->post('/dashboard/settings/mailboxes/import', function (Request $request) use ($app, $authorizeMailboxImport, $renderMailboxImport): Response {
    $user = $authorizeMailboxImport();
    if ($user instanceof Response) {
        return $user;
    }
    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    $upload = $request->file('profile');
    if ($upload === null || !$upload->valid()) {
        return $renderMailboxImport($user, error: 'Choose a valid .mobileconfig or XML plist file.');
    }
    if ($upload->size > 262144) {
        return $renderMailboxImport($user, error: 'Configuration profile must be 256 KiB or smaller.');
    }
    $contents = $upload->contents();
    if ($contents === null) {
        return $renderMailboxImport($user, error: 'Unable to read the uploaded configuration profile.');
    }

    try {
        $candidates = $app->make(MobileconfigAccountImporter::class)->import($contents);
        $store = $app->make(MailProfileImportStore::class);
        $store->prune();
        $token = $store->create($candidates);
        return $renderMailboxImport($user, array_map(static fn ($candidate): array => $candidate->toArray(), $candidates), $token);
    } catch (\Throwable $error) {
        return $renderMailboxImport($user, error: $error->getMessage());
    }
});

$router->post('/dashboard/settings/mailboxes/import/confirm', function (Request $request) use ($app, $authorizeMailboxImport, $renderMailboxImport): Response {
    $user = $authorizeMailboxImport();
    if ($user instanceof Response) {
        return $user;
    }
    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    $candidate = $app->make(MailProfileImportStore::class)->consume(
        (string) ($request->body['token'] ?? ''),
        (int) ($request->body['candidate'] ?? -1),
    );
    if ($candidate === null) {
        return $renderMailboxImport($user, error: 'The import review expired or was already used.');
    }

    try {
        $account = new MailboxAccount(
            id: strtolower(trim((string) ($request->body['id'] ?? ''))),
            label: trim((string) ($candidate['label'] ?? 'Mailbox')),
            host: trim((string) ($candidate['incoming_host'] ?? '')),
            port: (int) ($candidate['incoming_port'] ?? 993),
            encryption: 'ssl',
            mailbox: trim((string) ($candidate['incoming_mailbox'] ?? 'INBOX')) ?: 'INBOX',
            usernameSecret: trim((string) ($request->body['username_secret'] ?? '')),
            passwordSecret: trim((string) ($request->body['password_secret'] ?? '')),
            enabled: true,
        );
        $app->make(MailboxAccountStore::class)->create($account);
        return Response::redirect('/dashboard/settings/mailboxes?saved=1', 303);
    } catch (\Throwable $error) {
        return $renderMailboxImport($user, error: $error->getMessage());
    }
});

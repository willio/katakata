<?php

declare(strict_types=1);

use Katakata\Auth\Session;
use Katakata\Dashboard\DashboardSettings;
use Katakata\Distribution\SubscriberStore;
use Katakata\Distribution\UnavailableSubscriberStore;
use Katakata\Email\ImapSettings;
use Katakata\Email\Mailbox;
use Katakata\Http\Request;
use Katakata\Http\Response;
use Katakata\View;

/**
 * @var \Katakata\Http\Router $router
 * @var \Katakata\Application $app
 */

$authorizeSettings = static function () use ($app): array|Response {
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

$readiness = static function () use ($app): array {
    $settings = $app->make(DashboardSettings::class)->all();
    $discussion = $settings['discussion'] ?? [];
    $provider = (string) ($discussion['provider'] ?? 'none');
    $threadsReady = trim((string) $app->config()->get('threads.user_id', '')) !== ''
        && trim((string) $app->config()->get('threads.access_token', '')) !== '';
    $analyticsSecret = trim((string) $app->config()->get('analytics.secret', ''));
    $newsletterReady = !($app->make(SubscriberStore::class) instanceof UnavailableSubscriberStore);
    $imap = $app->make(ImapSettings::class);
    $mailbox = $app->make(Mailbox::class)->readiness();

    $discussionState = match ($provider) {
        'none' => ['status' => 'Disabled', 'detail' => 'Discussion is disabled.'],
        'native' => ['status' => 'Ready', 'detail' => 'Native discussion uses local operational storage.'],
        'threads' => $threadsReady
            ? ['status' => 'Ready', 'detail' => 'Threads credentials are present in deployment configuration.']
            : ['status' => 'Needs setup', 'detail' => 'Threads credentials are missing from deployment configuration.'],
        default => ['status' => 'Needs setup', 'detail' => 'The selected discussion provider is unavailable.'],
    };

    $mailboxState = match ($mailbox['status']) {
        'ready' => [
            'status' => 'Ready',
            'detail' => $mailbox['last_synced_at'] === null
                ? 'The private mailbox cache is available.'
                : 'Last synchronized ' . $mailbox['last_synced_at'] . '.',
        ],
        'error' => [
            'status' => 'Needs attention',
            'detail' => (string) ($mailbox['reason'] ?? 'The last scheduled mailbox synchronization failed.'),
        ],
        default => [
            'status' => 'Needs setup',
            'detail' => $imap->configured()
                ? 'Run and schedule private/jobs/sync-mail.php to populate the mailbox cache.'
                : 'Configure the required IMAP deployment variables, then schedule private/jobs/sync-mail.php.',
        ],
    };

    return [
        'newsletter' => $newsletterReady
            ? ['status' => 'Ready', 'detail' => 'Newsletter secret and subscriber storage are available.']
            : ['status' => 'Needs setup', 'detail' => 'Configure NEWSLETTER_SECRET or APP_KEY.'],
        'mailbox' => $mailboxState + [
            'configured' => $imap->configured(),
            'missing' => $imap->missing(),
            'host' => $imap->host,
            'port' => $imap->port,
            'encryption' => $imap->encryption,
            'mailbox' => $imap->mailbox,
            'last_synced_at' => $mailbox['last_synced_at'],
        ],
        'discussion' => $discussionState,
        'analytics' => $analyticsSecret !== ''
            ? ['status' => 'Ready', 'detail' => 'Privacy-bounded analytics hashing is configured.']
            : ['status' => 'Needs setup', 'detail' => 'Configure ANALYTICS_SECRET or APP_KEY.'],
        'appearance' => ['status' => 'Unavailable', 'detail' => 'Theme preferences are not applied by the current renderer.'],
        'account' => ['status' => 'Unavailable', 'detail' => 'Account and security management has no dashboard route yet.'],
        'system' => ['status' => 'Needs setup', 'detail' => 'Deployment diagnostics remain machine-managed.'],
    ];
};

$renderSettings = static function (array $user, bool $saved, ?string $error) use ($app, $readiness): Response {
    return Response::html($app->make(View::class)->render('dashboard-settings', [
        'user' => $user,
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'settings' => $app->make(DashboardSettings::class)->all(),
        'readiness' => $readiness(),
        'saved' => $saved,
        'error' => $error,
        'csrf' => $app->make(Session::class)->csrf(),
    ]), $error === null ? 200 : 422);
};

$router->get('/dashboard/settings', function (Request $request) use ($authorizeSettings, $renderSettings): Response {
    $authorization = $authorizeSettings();
    if ($authorization instanceof Response) {
        return $authorization;
    }

    return $renderSettings($authorization, ($request->query['saved'] ?? '') === '1', null);
});

$router->post('/dashboard/settings', function (Request $request) use ($app, $authorizeSettings, $renderSettings): Response {
    $authorization = $authorizeSettings();
    if ($authorization instanceof Response) {
        return $authorization;
    }

    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    $section = trim((string) ($request->body['section'] ?? ''));
    if ($section === 'appearance') {
        return $renderSettings($authorization, false, 'Appearance settings are unavailable until the renderer applies themes.');
    }

    try {
        $app->make(DashboardSettings::class)->update($section, $request->body);
    } catch (\Throwable $error) {
        return $renderSettings($authorization, false, $error->getMessage());
    }

    return Response::redirect('/dashboard/settings?saved=1#' . rawurlencode($section), 303);
});

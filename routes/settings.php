<?php

declare(strict_types=1);

use Katakata\Auth\Session;
use Katakata\Dashboard\DashboardSettings;
use Katakata\Distribution\SubscriberStore;
use Katakata\Distribution\UnavailableSubscriberStore;
use Katakata\Email\Mailbox;
use Katakata\Email\MailboxAccountStore;
use Katakata\Email\MailboxCredentialResolver;
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
    $mailbox = $app->make(Mailbox::class)->readiness();
    $accounts = $app->make(MailboxAccountStore::class)->all();
    $credentials = $app->make(MailboxCredentialResolver::class);

    $accountStates = [];
    foreach ($accounts as $account) {
        $state = null;
        foreach ((array) ($mailbox['accounts'] ?? []) as $candidate) {
            if (is_array($candidate) && ($candidate['account_id'] ?? null) === $account->id) {
                $state = $candidate;
                break;
            }
        }
        $missing = $credentials->missing($account);
        $accountStates[] = [
            'account_id' => $account->id,
            'label' => $account->label,
            'host' => $account->host,
            'port' => $account->port,
            'encryption' => $account->encryption,
            'mailbox' => $account->mailbox,
            'username_secret' => $account->usernameSecret,
            'password_secret' => $account->passwordSecret,
            'enabled' => $account->enabled,
            'configured' => $missing === [],
            'missing' => $missing,
            'status' => !$account->enabled ? 'disabled' : (string) ($state['status'] ?? ($missing === [] ? 'needs_setup' : 'needs_setup')),
            'reason' => !$account->enabled
                ? 'Mailbox account is disabled.'
                : (string) ($state['reason'] ?? ($missing === []
                    ? 'Run the scheduled mailbox synchronizer to populate this cache.'
                    : 'Deployment credential variables are missing.')),
            'last_synced_at' => $state['last_synced_at'] ?? null,
        ];
    }

    $discussionState = match ($provider) {
        'none' => ['status' => 'Disabled', 'detail' => 'Discussion is disabled.'],
        'native' => ['status' => 'Ready', 'detail' => 'Native discussion uses local operational storage.'],
        'threads' => $threadsReady
            ? ['status' => 'Ready', 'detail' => 'Threads credentials are present in deployment configuration.']
            : ['status' => 'Needs setup', 'detail' => 'Threads credentials are missing from deployment configuration.'],
        default => ['status' => 'Needs setup', 'detail' => 'The selected discussion provider is unavailable.'],
    };

    $mailboxState = match ((string) ($mailbox['status'] ?? 'disabled')) {
        'ready' => ['status' => 'Ready', 'detail' => 'All enabled mailbox caches are available.'],
        'partial' => ['status' => 'Partially available', 'detail' => 'Healthy mailbox caches remain available while another account needs attention.'],
        'needs_setup' => ['status' => 'Needs setup', 'detail' => 'No enabled mailbox account has a usable cache yet.'],
        default => ['status' => 'Disabled', 'detail' => 'No mailbox account is enabled.'],
    };

    return [
        'newsletter' => $newsletterReady
            ? ['status' => 'Ready', 'detail' => 'Newsletter secret and subscriber storage are available.']
            : ['status' => 'Needs setup', 'detail' => 'Configure NEWSLETTER_SECRET or APP_KEY.'],
        'mailbox' => $mailboxState + ['accounts' => $accountStates],
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
    return $renderSettings($authorization, ($request->query['saved'] ?? '') === '1', $request->query['error'] ?? null);
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

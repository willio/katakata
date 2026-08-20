<?php

declare(strict_types=1);

use Katakata\Auth\AccountStore;
use Katakata\Auth\Session;
use Katakata\Dashboard\DashboardSettings;
use Katakata\Distribution\SubscriberStore;
use Katakata\Distribution\UnavailableSubscriberStore;
use Katakata\Email\Mailbox;
use Katakata\Email\MailboxAccountStore;
use Katakata\Email\MailboxCredentialResolver;
use Katakata\Http\Request;
use Katakata\Http\Response;
use Katakata\Settings\SecretsStore;
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
    $threadsUserId = trim((string) ($discussion['threads_user_id'] ?? ''));
    if ($threadsUserId === '') {
        $threadsUserId = trim((string) $app->config()->get('threads.user_id', ''));
    }
    $threadsTokenSecret = trim((string) ($discussion['threads_token_secret'] ?? ''));
    $threadsSecrets = $app->make(SecretsStore::class);
    $threadsTokenManaged = $threadsSecrets->available() && $threadsSecrets->has('threads.access_token');
    $threadsMissing = [];
    if ($threadsUserId === '') {
        $threadsMissing[] = 'THREADS_USER_ID';
    }
    if ($threadsTokenManaged) {
        $threadsTokenPresent = true;
    } elseif ($threadsTokenSecret !== '') {
        $threadsToken = getenv($threadsTokenSecret);
        $threadsTokenPresent = is_string($threadsToken) && trim($threadsToken) !== '';
        if (!$threadsTokenPresent) {
            $threadsMissing[] = $threadsTokenSecret;
        }
    } else {
        $threadsTokenPresent = trim((string) $app->config()->get('threads.access_token', '')) !== '';
        if (!$threadsTokenPresent) {
            $threadsMissing[] = 'THREADS_ACCESS_TOKEN';
        }
    }
    $threadsTokenSource = $threadsTokenManaged
        ? 'Managed in settings'
        : ($threadsTokenPresent ? 'From deployment configuration' : 'Missing');
    $threadsReady = $threadsUserId !== '' && $threadsTokenPresent;
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
            ? ['status' => 'Ready', 'detail' => 'Threads credentials are present in settings or deployment configuration. Token source: ' . $threadsTokenSource . '.', 'missing' => []]
            : ['status' => 'Needs setup', 'detail' => 'Threads credentials are missing from settings and deployment configuration. Token source: ' . $threadsTokenSource . '.', 'missing' => $threadsMissing],
        default => ['status' => 'Needs setup', 'detail' => 'The selected discussion provider is unavailable.'],
    } + ['token_managed' => $threadsTokenManaged];

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
        'appearance' => ['status' => 'Partially available', 'detail' => 'Button shape is applied; theme preferences are not applied by the current renderer.'],
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
    $threadsTokenValue = trim((string) ($request->body['threads_token_value'] ?? ''));
    $threadsTokenRemove = filter_var($request->body['threads_token_remove'] ?? false, FILTER_VALIDATE_BOOL);
    if ($section === 'discussion' && ($threadsTokenValue !== '' || $threadsTokenRemove)) {
        $email = (string) ($authorization['email'] ?? '');
        $confirmed = $email !== '' && $app->make(AccountStore::class)->authenticate(
            $email,
            (string) ($request->body['confirm_password'] ?? ''),
        ) !== null;
        if (!$confirmed) {
            return $renderSettings($authorization, false, 'Confirm your current password to change the stored Threads token.');
        }
    }
    try {
        if ($section === 'discussion' && ($threadsTokenValue !== '' || $threadsTokenRemove)) {
            $secrets = $app->make(SecretsStore::class);
            try {
                if ($threadsTokenRemove) {
                    $secrets->remove('threads.access_token');
                } else {
                    $secrets->set('threads.access_token', $threadsTokenValue);
                }
            } catch (\RuntimeException) {
                return $renderSettings($authorization, false, 'The Threads token could not be stored because the application secret store is unavailable; configure APP_KEY in the deployment environment.');
            }
        }
        $app->make(DashboardSettings::class)->update($section, $request->body);
    } catch (\Throwable $error) {
        return $renderSettings($authorization, false, $error->getMessage());
    }
    return Response::redirect('/dashboard/settings?saved=1#' . rawurlencode($section), 303);
});

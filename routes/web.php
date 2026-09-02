<?php

declare(strict_types=1);

use Katakata\Analytics\VisitRecorder;
use Katakata\Auth\AccountStore;
use Katakata\Auth\Session;
use Katakata\Auth\WebAuthn;
use Katakata\Content\Repository;
use Katakata\Dashboard\DashboardAnalytics;
use Katakata\Dashboard\DashboardAttention;
use Katakata\Dashboard\DashboardBuzz;
use Katakata\Dashboard\DashboardSettings;
use Katakata\Distribution\ConfirmationMailer;
use Katakata\Distribution\ResendWebhook;
use Katakata\Distribution\SubscriberStore;
use Katakata\Http\Request;
use Katakata\Http\Response;
use Katakata\Rendering\Archive;
use Katakata\Rendering\AuthorArchive;
use Katakata\Rendering\AuthorResolver;
use Katakata\Rendering\Feed;
use Katakata\Rendering\Home;
use Katakata\Rendering\Markdown;
use Katakata\View;

/**
 * @var \Katakata\Http\Router $router
 * @var \Katakata\Application $app
 */

$router->get('/healthz', static fn (Request $request): Response => Response::json(['status' => 'ok']));

$recordVisit = static function (Request $request) use ($app): void {
    $app->make(VisitRecorder::class)->record($request);
};

$router->get('/', function (Request $request) use ($app, $recordVisit): Response {
    $recordVisit($request);
    $repository = $app->make(Repository::class);
    $layout = $app->make(Home::class)->layout($repository->posts());
    $lead = $layout['lead'];
    $authors = [];
    $authorResolver = $app->make(AuthorResolver::class);
    $leadAuthor = $lead === null ? null : $authorResolver->forPost($lead, $repository);
    foreach ($layout['months'] as $shelf) {
        foreach ($shelf['posts'] as $post) {
            $author = $authorResolver->forPost($post, $repository);
            if ($author !== null) {
                $authors[$author->slug] = $author;
            }
        }
    }

    return Response::html($app->make(View::class)->render('home', [
        'name' => (string) $app->config()->get('app.name', 'Katakata'),
        'tagline' => (string) $app->config()->get('app.tagline', ''),
        'siteUrl' => rtrim((string) $app->config()->get('app.url', 'http://localhost:8000'), '/'),
        ...$layout,
        'leadAuthor' => $leadAuthor,
        'authors' => $authors,
        'csrf' => $app->make(Session::class)->csrf(),
    ]));
});

$router->get('/archive', function (Request $request) use ($app, $recordVisit): Response {
    $recordVisit($request);
    $repository = $app->make(Repository::class);
    $query = trim($request->query['q'] ?? '');

    return Response::html($app->make(View::class)->render('archive', [
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'years' => $app->make(Archive::class)->years($repository->posts(), $query, $request->query['year'] ?? null, $request->query['month'] ?? null),
        'query' => $query,
        'year' => $request->query['year'] ?? null,
        'month' => $request->query['month'] ?? null,
    ]));
});

$router->get('/authors/{slug}', function (Request $request, string $slug) use ($app, $recordVisit): Response {
    $repository = $app->make(Repository::class);
    $author = $repository->findAuthor($slug);

    if ($author === null) {
        return Response::notFound();
    }

    $recordVisit($request);

    return Response::html($app->make(View::class)->render('author', [
        'author' => $author,
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'posts' => $app->make(AuthorArchive::class)->posts($repository->posts(), $slug),
        'bioHtml' => $author->bio === null ? null : $app->make(Markdown::class)->render($author->bio),
    ]));
});

$router->get('/feed.xml', function (Request $request) use ($app): Response {
    $feed = $app->make(Feed::class)->rss(
        $app->make(Repository::class)->posts(),
        (string) $app->config()->get('app.name', 'Katakata'),
        (string) $app->config()->get('app.url', 'http://localhost:8000'),
    );

    return new Response($feed, 200, ['Content-Type' => 'application/rss+xml; charset=utf-8']);
});

$router->get('/feed.json', function (Request $request) use ($app): Response {
    $feed = $app->make(Feed::class)->json(
        $app->make(Repository::class)->posts(),
        (string) $app->config()->get('app.name', 'Katakata'),
        (string) $app->config()->get('app.url', 'http://localhost:8000'),
    );

    return new Response($feed, 200, ['Content-Type' => 'application/feed+json; charset=utf-8']);
});

$renderNewsletter = static function (
    string $mode = 'subscribe',
    ?string $message = null,
    ?string $error = null,
    ?string $token = null,
) use ($app): Response {
    return Response::html($app->make(View::class)->render('newsletter', [
        'mode' => $mode,
        'message' => $message,
        'error' => $error,
        'token' => $token,
        'csrf' => $app->make(Session::class)->csrf(),
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
    ]), $error === null ? 200 : 422);
};

$router->post('/webhooks/resend', function (Request $request) use ($app): Response {
    try {
        $result = $app->make(ResendWebhook::class)->handle($request->rawBody, [
            'svix-id' => $request->header('svix-id') ?? '',
            'svix-timestamp' => $request->header('svix-timestamp') ?? '',
            'svix-signature' => $request->header('svix-signature') ?? '',
        ]);

        return Response::json(['received' => true, 'duplicate' => $result['duplicate']]);
    } catch (\InvalidArgumentException) {
        return Response::json(['error' => 'Invalid webhook.'], 400);
    } catch (\Throwable) {
        return Response::json(['error' => 'Webhook processing failed.'], 500);
    }
});

$router->get('/newsletter', fn (Request $request): Response => $renderNewsletter());
$router->post('/newsletter/subscribe', function (Request $request) use ($app, $renderNewsletter): Response {
    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return $renderNewsletter('subscribe', null, 'Email subscription form expired. Please try again.');
    }

    try {
        $subscription = $app->make(SubscriberStore::class)->request($request->body['email'] ?? '');
        $app->make(ConfirmationMailer::class)->queue($subscription);
    } catch (\InvalidArgumentException) {
        return $renderNewsletter('subscribe', null, 'Email is invalid. Please enter a valid address.');
    } catch (\Throwable) {
        return $renderNewsletter('subscribe', null, 'Newsletter is not configured.');
    }

    return $renderNewsletter('pending', 'Check your email for a confirmation link. Your subscription is not active until you confirm it.');
});
$router->get('/newsletter/confirm', function (Request $request) use ($app, $renderNewsletter): Response {
    try {
        $app->make(SubscriberStore::class)->confirm($request->query['token'] ?? '');
        return $renderNewsletter('confirmed', 'You are subscribed.');
    } catch (\Throwable) {
        return $renderNewsletter('confirmed', null, 'Confirmation link is invalid, expired, or newsletter delivery is not configured.');
    }
});
$router->get('/newsletter/unsubscribe', function (Request $request) use ($renderNewsletter): Response {
    $token = $request->query['token'] ?? '';
    return $token === ''
        ? $renderNewsletter('unsubscribe', null, 'Unsubscribe link is invalid.')
        : $renderNewsletter('unsubscribe', null, null, $token);
});
$router->post('/newsletter/unsubscribe', function (Request $request) use ($app, $renderNewsletter): Response {
    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return $renderNewsletter('unsubscribe', null, 'Unsubscribe form expired. Please try again.', $request->body['token'] ?? '');
    }

    try {
        $app->make(SubscriberStore::class)->unsubscribe($request->body['token'] ?? '');
        return $renderNewsletter('unsubscribed', 'You have been unsubscribed.');
    } catch (\Throwable) {
        return $renderNewsletter('unsubscribe', null, 'Unsubscribe link is invalid.');
    }
});

$renderAuth = static function (string $mode, ?string $error = null, ?string $token = null) use ($app): Response {
    $session = $app->make(Session::class);

    return Response::html($app->make(View::class)->render('auth', [
        'mode' => $mode,
        'error' => $error,
        'token' => $token,
        'csrf' => $session->csrf(),
        'buttonStyle' => (string) ($app->make(DashboardSettings::class)->section('appearance')['button_style'] ?? 'regular'),
    ]), $error === null ? 200 : 422);
};

$requireUser = static function () use ($app): ?array {
    return $app->make(Session::class)->user();
};

$router->get('/login', function (Request $request) use ($renderAuth, $requireUser): Response {
    return $requireUser() === null ? $renderAuth('login') : Response::redirect('/dashboard');
});
$router->post('/login', function (Request $request) use ($app, $renderAuth): Response {
    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return $renderAuth('login', 'The form expired. Please try again.');
    }

    $account = $app->make(AccountStore::class)->authenticate(
        $request->body['email'] ?? '',
        $request->body['password'] ?? '',
    );
    if ($account === null) {
        return $renderAuth('login', 'Email or password is incorrect.');
    }

    $session->login($account);
    return Response::redirect('/dashboard');
});
$router->post('/logout', function (Request $request) use ($app): Response {
    $session = $app->make(Session::class);
    if ($session->validCsrf($request->body['csrf'] ?? null)) {
        $session->logout();
    }

    return Response::redirect('/login');
});
$router->get('/register', function (Request $request) use ($renderAuth): Response {
    return $renderAuth('register', null, $request->query['token'] ?? '');
});
$router->post('/register', function (Request $request) use ($app, $renderAuth): Response {
    $session = $app->make(Session::class);
    $token = $request->body['token'] ?? '';
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return $renderAuth('register', 'The form expired. Please try again.', $token);
    }

    try {
        $account = $app->make(AccountStore::class)->accept(
            $token,
            $request->body['email'] ?? '',
            $request->body['password'] ?? '',
        );
        $session->login($account);
        return Response::redirect('/dashboard');
    } catch (\InvalidArgumentException $error) {
        return $renderAuth('register', $error->getMessage(), $token);
    } catch (\Throwable) {
        return $renderAuth('register', 'The invitation could not be accepted. Check the invitation link and email, then try again.', $token);
    }
});

$router->get('/dashboard', function (Request $request) use ($app, $requireUser): Response {
    $user = $requireUser();
    if ($user === null) {
        return Response::redirect('/login', 302);
    }

    $repository = $app->make(Repository::class);
    $posts = $repository->posts()->all();
    $drafts = $repository->drafts()->all();
    usort($drafts, static function ($left, $right): int {
        return ($right->updatedAt?->getTimestamp() ?? 0) <=> ($left->updatedAt?->getTimestamp() ?? 0);
    });

    $dashboardAnalytics = $app->make(DashboardAnalytics::class);
    $analytics = $dashboardAnalytics->summary();

    return Response::html($app->make(View::class)->render('dashboard', [
        'user' => $user,
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'recentDrafts' => array_slice($drafts, 0, 5),
        'latestPosts' => array_slice($posts, 0, 5),
        'cards' => $app->make(DashboardAttention::class)->cards(),
        'analytics' => $analytics,
        'recentVisits' => $dashboardAnalytics->recent($analytics),
        'buzz' => $app->make(DashboardBuzz::class)->recent(),
        'csrf' => $app->make(Session::class)->csrf(),
        'buttonStyle' => (string) ($app->make(DashboardSettings::class)->section('appearance')['button_style'] ?? 'regular'),
    ]));
});

$router->get('/analytics', function (Request $request) use ($app, $requireUser): Response {
    if ($requireUser() === null) {
        return Response::redirect('/login', 302);
    }

    $dashboardAnalytics = $app->make(DashboardAnalytics::class);
    $analytics = $dashboardAnalytics->summary();

    return Response::html($app->make(View::class)->render('analytics', [
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'analytics' => $analytics,
        'recentVisits' => $dashboardAnalytics->recent($analytics),
        'buttonStyle' => (string) ($app->make(DashboardSettings::class)->section('appearance')['button_style'] ?? 'regular'),
    ]));
});

$router->get('/dashboard/editor', fn (Request $request): Response => Response::redirect('/posts', 302));
$router->post('/dashboard/editor/save', fn (Request $request): Response => Response::json(['error' => 'Legacy editor endpoint retired.'], 410));
$router->post('/dashboard/editor/publish', fn (Request $request): Response => Response::json(['error' => 'Legacy editor endpoint retired.'], 410));
$router->get('/dashboard/editor/version', fn (Request $request): Response => Response::json(['error' => 'Legacy editor endpoint retired.'], 410));

$router->post('/passkeys/register/options', function (Request $request) use ($app, $requireUser): Response {
    $user = $requireUser();
    if ($user === null) {
        return Response::json(['error' => 'Unauthorised.'], 401);
    }
    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::json(['error' => 'The form expired. Please try again.'], 419);
    }

    try {
        $challenge = $session->beginPasskey('register', ['account_id' => (string) $user['id']]);
        return Response::json($app->make(WebAuthn::class)->registrationOptions($user, $challenge));
    } catch (\Throwable) {
        return Response::json(['error' => 'Passkey registration is not available. Please try again.'], 422);
    }
});
$router->post('/passkeys/register', function (Request $request) use ($app, $requireUser): Response {
    $user = $requireUser();
    if ($user === null) {
        return Response::json(['error' => 'Unauthorised.'], 401);
    }
    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::json(['error' => 'The form expired. Please try again.'], 419);
    }

    $pending = $session->consumePasskey('register');
    if ($pending === null || !hash_equals((string) ($pending['account_id'] ?? ''), (string) $user['id'])) {
        return Response::json(['error' => 'The passkey ceremony expired. Please try again.'], 422);
    }
    $credential = json_decode((string) ($request->body['credential'] ?? ''), true);
    if (!is_array($credential)) {
        return Response::json(['error' => 'The passkey response is invalid.'], 422);
    }

    try {
        $app->make(WebAuthn::class)->register(
            (string) $user['id'],
            (string) $pending['challenge'],
            array_map('strval', $credential),
        );
        return Response::json(['ok' => true]);
    } catch (\RuntimeException $error) {
        return Response::json(['error' => $error->getMessage()], 422);
    } catch (\Throwable) {
        return Response::json(['error' => 'Passkey registration failed. Please try again.'], 422);
    }
});
$router->post('/passkeys/login/options', function (Request $request) use ($app): Response {
    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::json(['error' => 'The form expired. Please try again.'], 419);
    }

    $account = $app->make(AccountStore::class)->findByEmail($request->body['email'] ?? '');
    if ($account === null) {
        return Response::json(['error' => 'Passkey sign-in is not available for this account.'], 422);
    }

    try {
        $challenge = $session->beginPasskey('login', ['account_id' => (string) $account['id']]);
        return Response::json($app->make(WebAuthn::class)->authenticationOptions($account, $challenge));
    } catch (\RuntimeException) {
        return Response::json(['error' => 'Passkey sign-in is not available for this account.'], 422);
    } catch (\Throwable) {
        return Response::json(['error' => 'Passkey sign-in is not available. Please try again.'], 422);
    }
});
$router->post('/passkeys/login', function (Request $request) use ($app): Response {
    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::json(['error' => 'The form expired. Please try again.'], 419);
    }

    $pending = $session->consumePasskey('login');
    if ($pending === null) {
        return Response::json(['error' => 'The passkey ceremony expired. Please try again.'], 422);
    }
    $credential = json_decode((string) ($request->body['credential'] ?? ''), true);
    if (!is_array($credential)) {
        return Response::json(['error' => 'The passkey response is invalid.'], 422);
    }
    $account = $app->make(AccountStore::class)->find((string) ($pending['account_id'] ?? ''));
    if ($account === null) {
        return Response::json(['error' => 'Passkey sign-in failed. Please try again.'], 422);
    }

    try {
        $app->make(WebAuthn::class)->authenticate(
            (string) $account['id'],
            (string) $pending['challenge'],
            array_map('strval', $credential),
        );
    } catch (\RuntimeException $error) {
        return Response::json(['error' => $error->getMessage()], 422);
    } catch (\Throwable) {
        return Response::json(['error' => 'Passkey sign-in failed. Please try again.'], 422);
    }

    $session->login($account);
    return Response::json(['ok' => true, 'redirect' => '/dashboard']);
});

<?php

declare(strict_types=1);

use Katakata\Analytics\VisitRecorder;
use Katakata\Auth\AccountStore;
use Katakata\Auth\Session;
use Katakata\Auth\WebAuthn;
use Katakata\Content\Repository;
use Katakata\Dashboard\DashboardAnalytics;
use Katakata\Dashboard\DashboardBuzz;
use Katakata\Editorial\DraftEditor;
use Katakata\Editorial\DraftVersion;
use Katakata\Editorial\Publisher;
use Katakata\Distribution\ConfirmationMailer;
use Katakata\Distribution\NewsletterDispatcher;
use Katakata\Distribution\ResendWebhook;
use Katakata\Distribution\SubscriberStore;
use Katakata\Http\Request;
use Katakata\Http\Response;
use Katakata\Rendering\Archive;
use Katakata\Rendering\AuthorArchive;
use Katakata\Rendering\Feed;
use Katakata\Rendering\Home;
use Katakata\Rendering\Markdown;
use Katakata\Seo\SeoChecker;
use Katakata\View;

/**
 * @var \Katakata\Http\Router $router
 * @var \Katakata\Application $app
 */

$recordVisit = static function (Request $request) use ($app): void {
    $app->make(VisitRecorder::class)->record($request);
};

$router->get('/', function (Request $request) use ($app, $recordVisit): Response {
    $recordVisit($request);
    $repository = $app->make(Repository::class);
    $posts = $app->make(Home::class)->latest($repository->posts());
    $authors = [];

    foreach ($posts as $post) {
        if ($post->author !== null) {
            $authors[$post->slug] = $repository->findAuthor($post->author);
        }
    }

    return Response::html($app->make(View::class)->render('home', [
        'name' => (string) $app->config()->get('app.name', 'Katakata'),
        'tagline' => (string) $app->config()->get('app.tagline', ''),
        'siteUrl' => rtrim((string) $app->config()->get('app.url', 'http://localhost:8000'), '/'),
        'posts' => $posts,
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
        'years' => $app->make(Archive::class)->years($repository->posts(), $query),
        'query' => $query,
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

        return Response::json([
            'received' => true,
            'duplicate' => $result['duplicate'],
        ]);
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
        // Deliberately hide whether the address already exists.
    }

    return $renderNewsletter(
        'pending',
        'Check your email for a confirmation link. Your subscription is not active until you confirm it.',
    );
});

$router->get('/newsletter/confirm', function (Request $request) use ($app, $renderNewsletter): Response {
    try {
        $app->make(SubscriberStore::class)->confirm($request->query['token'] ?? '');
        return $renderNewsletter('confirmed', 'You are subscribed.');
    } catch (\Throwable) {
        return $renderNewsletter('confirmed', null, 'Confirmation link is invalid or expired.');
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
        return $renderNewsletter(
            'unsubscribe',
            null,
            'Unsubscribe form expired. Please try again.',
            $request->body['token'] ?? '',
        );
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
    } catch (\Throwable $error) {
        return $renderAuth('register', $error->getMessage(), $token);
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
        'publishedCount' => count($posts),
        'draftCount' => count($drafts),
        'analytics' => $analytics,
        'recentVisits' => $dashboardAnalytics->recent($analytics),
        'buzz' => $app->make(DashboardBuzz::class)->recent(),
        'seo' => $app->make(SeoChecker::class)->check(),
        'csrf' => $app->make(Session::class)->csrf(),
    ]));
});

$router->get('/dashboard/editor', function (Request $request) use ($app, $requireUser): Response {
    if ($requireUser() === null) {
        return Response::redirect('/login', 302);
    }

    return Response::html($app->make(View::class)->render('editor', [
        'draft' => $app->make(DraftEditor::class)->open($request->query['slug'] ?? null),
        'csrf' => $app->make(Session::class)->csrf(),
    ]));
});

$router->post('/dashboard/editor/save', function (Request $request) use ($app, $requireUser): Response {
    if ($requireUser() === null) {
        return Response::json(['error' => 'Unauthorised.'], 401);
    }

    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::json(['error' => 'The editor session expired.'], 422);
    }

    try {
        $draft = $app->make(DraftEditor::class)->save($request->body);
        return Response::json([
            'saved' => true,
            'slug' => $draft->slug,
            'updated_at' => $draft->updatedAt?->format(DATE_ATOM),
        ]);
    } catch (\Throwable $error) {
        return Response::json(['error' => $error->getMessage()], 422);
    }
});

$router->post('/dashboard/editor/publish', function (Request $request) use ($app, $requireUser): Response {
    if ($requireUser() === null) {
        return Response::redirect('/login', 302);
    }

    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::redirect('/dashboard/editor?error=expired', 302);
    }

    try {
        $post = $app->make(Publisher::class)->publish($request->body['slug'] ?? '');
        $app->make(NewsletterDispatcher::class)->queue($post);
        return Response::redirect($post->url(), 302);
    } catch (\Throwable $error) {
        return Response::redirect('/dashboard/editor?error=' . rawurlencode($error->getMessage()), 302);
    }
});

$router->get('/dashboard/editor/version', function (Request $request) use ($app, $requireUser): Response {
    if ($requireUser() === null) {
        return Response::json(['error' => 'Unauthorised.'], 401);
    }

    try {
        return Response::json([
            'body' => $app->make(DraftVersion::class)->read(
                $request->query['slug'] ?? '',
                $request->query['version'] ?? '',
            ),
        ]);
    } catch (\Throwable $error) {
        return Response::json(['error' => $error->getMessage()], 404);
    }
});

$router->post('/dashboard/passkeys/options', function (Request $request) use ($app, $requireUser): Response {
    $user = $requireUser();
    if ($user === null) {
        return Response::json(['error' => 'Unauthorised.'], 401);
    }

    try {
        return Response::json($app->make(WebAuthn::class)->registrationOptions($user));
    } catch (\Throwable $error) {
        return Response::json(['error' => $error->getMessage()], 422);
    }
});

$router->post('/dashboard/passkeys/register', function (Request $request) use ($app, $requireUser): Response {
    $user = $requireUser();
    if ($user === null) {
        return Response::json(['error' => 'Unauthorised.'], 401);
    }

    try {
        $app->make(WebAuthn::class)->register($user, $request->rawBody);
        return Response::json(['registered' => true]);
    } catch (\Throwable $error) {
        return Response::json(['error' => $error->getMessage()], 422);
    }
});

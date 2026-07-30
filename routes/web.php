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

    return Response::html($app->make(View::class)->render('archive', [
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'years' => $app->make(Archive::class)->years($repository->posts()),
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

$router->get('/{year}/{month}/{slug}', function (
    Request $request,
    string $year,
    string $month,
    string $slug,
) use ($app, $recordVisit): Response {
    if (!preg_match('/^\d{4}$/', $year) || !preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
        return Response::notFound();
    }

    $post = $app->make(Repository::class)->findPost($slug);

    if ($post === null || !$post->isPublished() || $post->url() !== "/{$year}/{$month}/{$slug}") {
        return Response::notFound();
    }

    $author = $post->author === null ? null : $app->make(Repository::class)->findAuthor($post->author);
    $recordVisit($request);

    return Response::html($app->make(View::class)->render('article', [
        'post' => $post,
        'author' => $author,
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'bodyHtml' => $app->make(Markdown::class)->render($post->body),
        'authorBioHtml' => $author?->bio === null ? null : $app->make(Markdown::class)->render($author->bio),
    ]));
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
    $dashboardAnalytics = $app->make(DashboardAnalytics::class);
    $analytics = $dashboardAnalytics->summary();
    usort($drafts, static function ($left, $right): int {
        return ($right->updatedAt?->getTimestamp() ?? 0) <=> ($left->updatedAt?->getTimestamp() ?? 0);
    });

    return Response::html($app->make(View::class)->render('dashboard', [
        'user' => $user,
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'publishedCount' => count($posts),
        'draftCount' => count($drafts),
        'recentDrafts' => array_slice($drafts, 0, 5),
        'latestPosts' => array_slice($posts, 0, 5),
        'seo' => $app->make(SeoChecker::class)->check(),
        'analytics' => $analytics,
        'recentVisits' => $dashboardAnalytics->recent($analytics),
        'buzz' => $app->make(DashboardBuzz::class)->recent(),
        'csrf' => $app->make(Session::class)->csrf(),
    ]));
});

$renderEditor = static function (?\Katakata\Content\Draft $draft = null, ?string $notice = null) use ($app, $requireUser): Response {
    $user = $requireUser();
    if ($user === null) {
        return Response::redirect('/login', 302);
    }

    return Response::html($app->make(View::class)->render('editor', [
        'user' => $user,
        'drafts' => $app->make(Repository::class)->drafts(),
        'draft' => $draft,
        'csrf' => $app->make(Session::class)->csrf(),
        'canInvite' => $app->make(Session::class)->canInvite(),
        'notice' => $notice,
        'draftVersion' => $draft === null ? '' : DraftVersion::of($draft),
    ]));
};

$router->get('/editor', fn (Request $request): Response => $renderEditor());
$router->get('/editor/new', fn (Request $request): Response => $renderEditor());
$router->get('/editor/drafts/{slug}', function (Request $request, string $slug) use ($app, $renderEditor): Response {
    $draft = $app->make(Repository::class)->findDraft($slug);
    return $draft === null ? Response::notFound() : $renderEditor($draft);
});

$router->post('/editor/drafts', function (Request $request) use ($app, $requireUser): Response {
    if ($requireUser() === null) {
        return Response::redirect('/login', 302);
    }
    if (!$app->make(Session::class)->validCsrf($request->body['csrf'] ?? null)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    $slug = $request->body['slug'] ?? '';
    $existing = $app->make(Repository::class)->findDraft($slug);
    $meta = $existing?->meta ?? [];
    unset($meta['title'], $meta['updated_at']);
    $app->make(DraftEditor::class)->save($slug, $request->body['title'] ?? '', $request->body['body'] ?? '', $meta);
    $app->make(Repository::class)->refresh();

    return Response::redirect('/editor/drafts/' . rawurlencode($slug));
});

$router->post('/editor/drafts/{slug}/autosave', function (Request $request, string $slug) use ($app, $requireUser): Response {
    if ($requireUser() === null) {
        return Response::json(['error' => 'Authentication required.'], 401);
    }
    if (!$app->make(Session::class)->validCsrf($request->body['csrf'] ?? null)) {
        return Response::json(['error' => 'Invalid CSRF token.'], 419);
    }

    $existing = $app->make(Repository::class)->findDraft($slug);
    if ($existing === null || !hash_equals($slug, $request->body['slug'] ?? '')) {
        return Response::json(['error' => 'Draft was not found.'], 404);
    }

    try {
        $meta = $existing->meta;
        unset($meta['title'], $meta['updated_at']);
        $app->make(DraftEditor::class)->save(
            $slug,
            $request->body['title'] ?? '',
            $request->body['body'] ?? '',
            $meta,
        );
        $repository = $app->make(Repository::class);
        $repository->refresh();
        $saved = $repository->findDraft($slug);
        if ($saved === null) {
            throw new RuntimeException('Saved draft could not be reloaded.');
        }

        return Response::json([
            'version' => DraftVersion::of($saved),
            'updated_at' => $saved->updatedAt?->format(DATE_ATOM),
            'client_version' => $request->body['client_version'] ?? '',
        ]);
    } catch (Throwable $error) {
        return Response::json(['error' => $error->getMessage()], 422);
    }
});

$router->post('/editor/drafts/{slug}/publish', function (Request $request, string $slug) use ($app, $requireUser): Response {
    if ($requireUser() === null) {
        return Response::redirect('/login', 302);
    }
    if (!$app->make(Session::class)->validCsrf($request->body['csrf'] ?? null)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    $draft = $app->make(Repository::class)->findDraft($slug);
    if ($draft === null) {
        return Response::notFound();
    }

    $app->make(Publisher::class)->publish($draft);
    $repository = $app->make(Repository::class);
    $repository->refresh();
    $post = $repository->findPost($draft->slug);
    if ($post !== null) {
        try {
            $app->make(NewsletterDispatcher::class)->dispatch($post);
        } catch (\Throwable) {
            // Publication is canonical; downstream queue failure must not roll it back.
        }
    }
    return Response::redirect('/archive');
});

$router->post('/editor/invitations', function (Request $request) use ($app): Response {
    $session = $app->make(Session::class);
    if (!$session->canInvite()) {
        return Response::html('Forbidden', 403);
    }
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    $invite = $app->make(AccountStore::class)->invite(
        $request->body['email'] ?? '',
        $request->body['role'] ?? 'editor',
    );
    $url = rtrim((string) $app->config()->get('app.url', 'http://localhost:8000'), '/');
    return Response::html('Invitation: ' . e($url . '/register?token=' . $invite['token']));
});


$decodeCredential = static function (mixed $value): array {
    if (!is_string($value)) {
        throw new RuntimeException('Passkey response is missing.');
    }
    $decoded = json_decode($value, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Passkey response is invalid.');
    }
    foreach ($decoded as $key => $item) {
        if (!is_string($key) || !is_string($item)) {
            throw new RuntimeException('Passkey response is invalid.');
        }
    }
    return $decoded;
};

$router->post('/passkeys/register/options', function (Request $request) use ($app): Response {
    $session = $app->make(Session::class);
    $account = $session->user();
    if ($account === null) {
        return Response::json(['error' => 'Authentication required.'], 401);
    }
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::json(['error' => 'Invalid CSRF token.'], 419);
    }

    $challenge = $session->beginPasskey('register', ['account_id' => (string) $account['id']]);
    return Response::json($app->make(WebAuthn::class)->registrationOptions($account, $challenge));
});

$router->post('/passkeys/register', function (Request $request) use ($app, $decodeCredential): Response {
    $session = $app->make(Session::class);
    $account = $session->user();
    if ($account === null) {
        return Response::json(['error' => 'Authentication required.'], 401);
    }
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::json(['error' => 'Invalid CSRF token.'], 419);
    }

    try {
        $ceremony = $session->consumePasskey('register');
        if ($ceremony === null || !hash_equals((string) $account['id'], (string) ($ceremony['account_id'] ?? ''))) {
            throw new RuntimeException('Passkey registration expired.');
        }
        $app->make(WebAuthn::class)->register(
            (string) $account['id'],
            (string) $ceremony['challenge'],
            $decodeCredential($request->body['credential'] ?? null),
        );
        return Response::json(['ok' => true]);
    } catch (Throwable $error) {
        return Response::json(['error' => $error->getMessage()], 422);
    }
});

$router->post('/passkeys/login/options', function (Request $request) use ($app): Response {
    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::json(['error' => 'Invalid CSRF token.'], 419);
    }

    try {
        $account = $app->make(AccountStore::class)->findByEmail($request->body['email'] ?? '');
        if ($account === null) {
            throw new RuntimeException('No passkey is registered for this account.');
        }
        $challenge = $session->beginPasskey('login', ['account_id' => (string) $account['id']]);
        return Response::json($app->make(WebAuthn::class)->authenticationOptions($account, $challenge));
    } catch (Throwable $error) {
        return Response::json(['error' => $error->getMessage()], 422);
    }
});

$router->post('/passkeys/login', function (Request $request) use ($app, $decodeCredential): Response {
    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::json(['error' => 'Invalid CSRF token.'], 419);
    }

    try {
        $ceremony = $session->consumePasskey('login');
        $account = is_array($ceremony)
            ? $app->make(AccountStore::class)->find((string) ($ceremony['account_id'] ?? ''))
            : null;
        if ($account === null) {
            throw new RuntimeException('Passkey authentication expired.');
        }
        $app->make(WebAuthn::class)->authenticate(
            (string) $account['id'],
            (string) $ceremony['challenge'],
            $decodeCredential($request->body['credential'] ?? null),
        );
        $session->login($account);
        return Response::json(['ok' => true, 'redirect' => '/editor']);
    } catch (Throwable $error) {
        return Response::json(['error' => $error->getMessage()], 422);
    }
});

$router->get('/healthz', function (Request $request): Response {
    return Response::json(['status' => 'ok']);
});

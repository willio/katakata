<?php

declare(strict_types=1);

use Katakata\Auth\AccountStore;
use Katakata\Auth\Session;
use Katakata\Auth\WebAuthn;
use Katakata\Content\Repository;
use Katakata\Editorial\DraftEditor;
use Katakata\Editorial\Publisher;
use Katakata\Http\Request;
use Katakata\Http\Response;
use Katakata\Rendering\Archive;
use Katakata\Rendering\AuthorArchive;
use Katakata\Rendering\Feed;
use Katakata\Rendering\Markdown;
use Katakata\View;

/**
 * @var \Katakata\Http\Router $router
 * @var \Katakata\Application $app
 */

$router->get('/', function (Request $request) use ($app): Response {
    return Response::html($app->make(View::class)->render('home', [
        'name' => (string) $app->config()->get('app.name', 'Katakata'),
        'tagline' => (string) $app->config()->get('app.tagline', ''),
    ]));
});

$router->get('/archive', function (Request $request) use ($app): Response {
    $repository = $app->make(Repository::class);

    return Response::html($app->make(View::class)->render('archive', [
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'years' => $app->make(Archive::class)->years($repository->posts()),
    ]));
});

$router->get('/authors/{slug}', function (Request $request, string $slug) use ($app): Response {
    $repository = $app->make(Repository::class);
    $author = $repository->findAuthor($slug);

    if ($author === null) {
        return Response::notFound();
    }

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

$router->get('/{year}/{month}/{slug}', function (
    Request $request,
    string $year,
    string $month,
    string $slug,
) use ($app): Response {
    if (!preg_match('/^\d{4}$/', $year) || !preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
        return Response::notFound();
    }

    $post = $app->make(Repository::class)->findPost($slug);

    if ($post === null || !$post->isPublished() || $post->url() !== "/{$year}/{$month}/{$slug}") {
        return Response::notFound();
    }

    $author = $post->author === null ? null : $app->make(Repository::class)->findAuthor($post->author);

    return Response::html($app->make(View::class)->render('article', [
        'post' => $post,
        'author' => $author,
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'bodyHtml' => $app->make(Markdown::class)->render($post->body),
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
    return $requireUser() === null ? $renderAuth('login') : Response::redirect('/editor');
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
    return Response::redirect('/editor');
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
        return Response::redirect('/editor');
    } catch (\Throwable $error) {
        return $renderAuth('register', $error->getMessage(), $token);
    }
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
    $app->make(Repository::class)->refresh();
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

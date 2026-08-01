<?php

declare(strict_types=1);

use Katakata\Auth\Session;
use Katakata\Content\Repository;
use Katakata\Editorial\DraftEditor;
use Katakata\Editorial\DraftVersion;
use Katakata\Editorial\Publisher;
use Katakata\Http\Request;
use Katakata\Http\Response;
use Katakata\View;

/**
 * @var \Katakata\Http\Router $router
 * @var \Katakata\Application $app
 */

$requireEditorUser = static function () use ($app): ?array {
    return $app->make(Session::class)->user();
};

$renderEditor = static function (?\Katakata\Content\Draft $draft = null, ?string $notice = null) use ($app, $requireEditorUser): Response {
    $user = $requireEditorUser();
    if ($user === null) {
        return Response::redirect('/login', 302);
    }

    return Response::html($app->make(View::class)->render('editor', [
        'drafts' => $app->make(Repository::class)->drafts(),
        'draft' => $draft,
        'csrf' => $app->make(Session::class)->csrf(),
        'notice' => $notice,
        'draftVersion' => $draft === null ? '' : DraftVersion::of($draft),
    ]));
};

$slugifyDraftTitle = static function (string $title): string {
    $title = trim($title);
    $ascii = function_exists('transliterator_transliterate')
        ? transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $title)
        : strtolower($title);
    $slug = preg_replace('/[^a-z0-9]+/', '-', (string) $ascii) ?? '';
    $slug = trim(substr($slug, 0, 120), '-');

    if ($slug !== '') {
        return $slug;
    }

    return 'post-' . substr(hash('sha256', $title), 0, 10);
};

$uniqueDraftSlug = static function (Repository $repository, string $base): string {
    $slug = $base;
    $suffix = 2;

    while ($repository->findDraft($slug) !== null || $repository->findPost($slug) !== null) {
        $slug = substr($base, 0, 110) . '-' . $suffix;
        $suffix++;
    }

    return $slug;
};

$draftMeta = static function (array $source, ?\Katakata\Content\Draft $existing = null): array {
    $meta = $existing?->meta ?? [];
    unset($meta['title'], $meta['updated_at']);
    $meta['publish_as_newsletter'] = isset($source['publish_as_newsletter']) ? 'true' : 'false';
    $meta['discussion_enabled'] = isset($source['discussion_enabled']) ? 'true' : 'false';

    return $meta;
};

$router->get('/editor', fn (Request $request): Response => $renderEditor());
$router->get('/editor/new', fn (Request $request): Response => $renderEditor());
$router->get('/editor/drafts/{slug}', function (Request $request, string $slug) use ($app, $renderEditor): Response {
    $draft = $app->make(Repository::class)->findDraft($slug);
    return $draft === null ? Response::notFound() : $renderEditor($draft);
});

// Compatibility redirects for links and browser history from the earlier draft surface.
$router->get('/drafts', fn (Request $request): Response => Response::redirect('/editor', 302));
$router->get('/drafts/{slug}', fn (Request $request, string $slug): Response => Response::redirect('/editor/drafts/' . rawurlencode($slug), 302));

$router->post('/editor/drafts', function (Request $request) use ($app, $requireEditorUser, $slugifyDraftTitle, $uniqueDraftSlug, $draftMeta): Response {
    if ($requireEditorUser() === null) {
        return Response::redirect('/login', 302);
    }

    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::json(['error' => 'The editor session expired.'], 419);
    }

    try {
        $requestedSlug = trim((string) ($request->body['slug'] ?? ''));
        $title = trim((string) ($request->body['title'] ?? ''));
        $body = (string) ($request->body['body'] ?? '');
        $repository = $app->make(Repository::class);
        $existing = $requestedSlug === '' ? null : $repository->findDraft($requestedSlug);
        $slug = $requestedSlug;

        if ($slug === '') {
            $slug = $uniqueDraftSlug($repository, $slugifyDraftTitle($title));
        }

        $app->make(DraftEditor::class)->save($slug, $title, $body, $draftMeta($request->body, $existing));
        $repository->refresh();
        $saved = $repository->findDraft($slug);

        if ($request->header('Accept') === 'application/json') {
            return Response::json([
                'created' => true,
                'slug' => $slug,
                'autosave_url' => '/editor/drafts/' . rawurlencode($slug) . '/autosave',
                'publish_url' => '/editor/drafts/' . rawurlencode($slug) . '/publish',
                'updated_at' => $saved?->updatedAt?->format(DATE_ATOM),
                'version' => $saved === null ? '' : DraftVersion::of($saved),
                'client_version' => (string) ($request->body['client_version'] ?? ''),
            ], 201);
        }

        return Response::redirect('/editor/drafts/' . rawurlencode($slug), 302);
    } catch (\Throwable $error) {
        return Response::json(['error' => $error->getMessage()], 422);
    }
});

$router->post('/editor/drafts/{slug}/autosave', function (Request $request, string $slug) use ($app, $requireEditorUser, $draftMeta): Response {
    if ($requireEditorUser() === null) {
        return Response::json(['error' => 'Authentication required.'], 401);
    }

    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::json(['error' => 'The editor session expired.'], 419);
    }

    try {
        $repository = $app->make(Repository::class);
        $existing = $repository->findDraft($slug);
        if ($existing === null) {
            return Response::json(['error' => 'Draft not found.'], 404);
        }

        $title = trim((string) ($request->body['title'] ?? $existing->title));
        $body = (string) ($request->body['body'] ?? $existing->body);

        $app->make(DraftEditor::class)->save($slug, $title, $body, $draftMeta($request->body, $existing));
        $repository->refresh();
        $saved = $repository->findDraft($slug);

        return Response::json([
            'saved' => true,
            'slug' => $slug,
            'updated_at' => $saved?->updatedAt?->format(DATE_ATOM),
            'version' => $saved === null ? '' : DraftVersion::of($saved),
            'client_version' => (string) ($request->body['client_version'] ?? ''),
        ]);
    } catch (\Throwable $error) {
        return Response::json(['error' => $error->getMessage()], 422);
    }
});

$router->post('/editor/drafts/{slug}/publish', function (Request $request, string $slug) use ($app, $requireEditorUser): Response {
    if ($requireEditorUser() === null) {
        return Response::redirect('/login', 302);
    }

    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::redirect('/editor/drafts/' . rawurlencode($slug) . '?error=expired', 302);
    }

    try {
        $repository = $app->make(Repository::class);
        $draft = $repository->findDraft($slug);
        if ($draft === null) {
            return Response::notFound();
        }

        $app->make(Publisher::class)->publish($draft);
        $repository->refresh();
        $post = $repository->findPost($slug);

        return Response::redirect($post?->url() ?? '/dashboard', 302);
    } catch (\Throwable $error) {
        return Response::redirect('/editor/drafts/' . rawurlencode($slug) . '?error=' . rawurlencode($error->getMessage()), 302);
    }
});

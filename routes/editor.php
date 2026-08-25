<?php

declare(strict_types=1);

use Katakata\Auth\Session;
use Katakata\Content\Repository;
use Katakata\Dashboard\DashboardSettings;
use Katakata\Editorial\DraftEditor;
use Katakata\Editorial\DraftVersion;
use Katakata\Editorial\ContentTrash;
use Katakata\Editorial\Publisher;
use Katakata\Http\Request;
use Katakata\Http\Response;
use Katakata\Mail\CampaignDraftFactory;
use Katakata\Mail\CampaignDraftStore;
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
        'buttonStyle' => (string) ($app->make(DashboardSettings::class)->section('appearance')['button_style'] ?? 'regular'),
    ]));
};

$renderPosts = static function (Request $request) use ($app, $requireEditorUser): Response {
    $user = $requireEditorUser();
    if ($user === null) {
        return Response::redirect('/login', 302);
    }

    $status = is_string($request->query['status'] ?? null) ? trim($request->query['status']) : 'all';
    if (!in_array($status, ['all', 'drafts', 'scheduled', 'published', 'trash'], true)) {
        $status = 'all';
    }
    $search = is_string($request->query['q'] ?? null) ? trim($request->query['q']) : '';

    return Response::html($app->make(View::class)->render('posts', [
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'status' => $status,
        'search' => $search,
        'drafts' => $app->make(Repository::class)->drafts()->all(),
        'posts' => $app->make(Repository::class)->posts()->all(),
        'trashItems' => $app->make(ContentTrash::class)->all(),
        'canManagePublished' => in_array($user['role'] ?? null, ['owner', 'admin'], true),
        'csrf' => $app->make(Session::class)->csrf(),
        'buttonStyle' => (string) ($app->make(DashboardSettings::class)->section('appearance')['button_style'] ?? 'regular'),
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

$router->get('/posts', $renderPosts);
$router->get('/editor', fn (Request $request): Response => Response::redirect('/posts', 302));
$router->get('/editor/new', fn (Request $request): Response => $renderEditor());
$router->get('/editor/drafts/{slug}', function (Request $request, string $slug) use ($app, $renderEditor): Response {
    $draft = $app->make(Repository::class)->findDraft($slug);
    return $draft === null ? Response::notFound() : $renderEditor($draft);
});

$router->post('/editor/posts/{slug}/campaign-drafts', function (Request $request, string $slug) use ($app): Response {
    $session = $app->make(Session::class);
    $user = $session->user();
    if ($user === null) {
        return Response::redirect('/login', 302);
    }
    if (!$session->canManageMail()) {
        return Response::html('Forbidden.', 403);
    }
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    $repository = $app->make(Repository::class);
    $post = $repository->findPost($slug);
    if ($post === null) {
        return Response::notFound();
    }

    $before = (string) file_get_contents($post->path);
    $draft = $app->make(CampaignDraftFactory::class)->fromPost(
        $post,
        (string) ($user['email'] ?? $user['id'] ?? 'unknown'),
    );
    $app->make(CampaignDraftStore::class)->create($draft);
    $after = (string) file_get_contents($post->path);

    if (!hash_equals(hash('sha256', $before), hash('sha256', $after))) {
        return Response::html('Source post changed while creating campaign draft.', 500);
    }

    return Response::redirect('/mail/campaign-drafts/' . rawurlencode($draft->id), 303);
});

// Compatibility redirects for links and browser history from the earlier draft surface.
$router->get('/drafts', fn (Request $request): Response => Response::redirect('/posts?status=drafts', 302));
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

$router->post('/editor/drafts/{slug}/trash', function (Request $request, string $slug) use ($app, $requireEditorUser): Response {
    $user = $requireEditorUser();
    if ($user === null) {
        return Response::redirect('/login', 302);
    }
    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::html('Invalid CSRF token.', 419);
    }
    $repository = $app->make(Repository::class);
    $draft = $repository->findDraft($slug);
    if ($draft === null) {
        return Response::notFound();
    }
    try {
        $app->make(ContentTrash::class)->trashDraft($draft, (string) ($user['id'] ?? $user['email'] ?? 'editor'));
        $repository->refresh();
        return Response::redirect('/posts?status=trash', 303);
    } catch (\Throwable) {
        return Response::html('Unable to move this draft to Trash.', 422);
    }
});

$router->post('/editor/posts/{slug}/trash', function (Request $request, string $slug) use ($app): Response {
    $session = $app->make(Session::class);
    $user = $session->user();
    if ($user === null) {
        return Response::redirect('/login', 302);
    }
    if (!$session->canManageSettings()) {
        return Response::html('Forbidden.', 403);
    }
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::html('Invalid CSRF token.', 419);
    }
    $repository = $app->make(Repository::class);
    $post = $repository->findPost($slug);
    if ($post === null) {
        return Response::notFound();
    }
    try {
        $app->make(ContentTrash::class)->trashPost($post, (string) ($user['id'] ?? $user['email'] ?? 'owner'));
        $repository->refresh();
        return Response::redirect('/posts?status=trash', 303);
    } catch (\Throwable) {
        return Response::html('Unable to move this article to Trash.', 422);
    }
});

$router->post('/editor/trash/{type}/{id}/restore', function (Request $request, string $type, string $id) use ($app, $requireEditorUser): Response {
    $user = $requireEditorUser();
    if ($user === null) {
        return Response::redirect('/login', 302);
    }
    $session = $app->make(Session::class);
    if ($type === 'post' && !$session->canManageSettings()) {
        return Response::html('Forbidden.', 403);
    }
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::html('Invalid CSRF token.', 419);
    }
    try {
        $app->make(ContentTrash::class)->restore($type, $id);
        $app->make(Repository::class)->refresh();
        return Response::redirect('/posts?status=trash', 303);
    } catch (\Throwable) {
        return Response::html('Unable to restore this content because its destination is occupied or the Trash copy is invalid.', 422);
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

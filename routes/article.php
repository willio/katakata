<?php

declare(strict_types=1);

use Katakata\Content\Repository;
use Katakata\Discussion\NativeDiscussionService;
use Katakata\Http\Request;
use Katakata\Http\Response;
use Katakata\Rendering\Markdown;
use Katakata\View;

/**
 * @var \Katakata\Http\Router $router
 * @var \Katakata\Application $app
 */

$router->post('/{year}/{month}/{slug}/discussion', function (
    Request $request,
    string $year,
    string $month,
    string $slug,
) use ($app): Response {
    $post = $app->make(Repository::class)->findPost($slug);
    if ($post === null || !$post->isPublished() || $post->url() !== "/{$year}/{$month}/{$slug}") {
        return Response::notFound();
    }

    $location = $post->url();
    $session = $app->make(\Katakata\Auth\Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::redirect($location . '?comment=expired#discussion', 303);
    }

    try {
        $app->make(\Katakata\Discussion\NativeDiscussionService::class)->submit(
            $post,
            $request->body['author_name'] ?? '',
            $request->body['body'] ?? '',
            $request->body['parent_id'] ?? null,
            spam: ['honeypot' => $request->body['honeypot'] ?? null],
        );

        return Response::redirect($post->url() . '?comment=pending#discussion', 303);
    } catch (\Throwable) {
        return Response::redirect($post->url() . '?comment=invalid#discussion', 303);
    }
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
    $discussion = $app->make(NativeDiscussionService::class)->forPost($post);
    $app->make(\Katakata\Analytics\VisitRecorder::class)->record($request);

    return Response::html($app->make(View::class)->render('article', [
        'post' => $post,
        'author' => $author,
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'bodyHtml' => $app->make(Markdown::class)->render($post->body),
        'authorBioHtml' => $author?->bio === null ? null : $app->make(Markdown::class)->render($author->bio),
        'discussion' => $discussion,
        'commentState' => (string) ($request->query['comment'] ?? ''),
        'csrf' => $app->make(\Katakata\Auth\Session::class)->csrf(),
    ]));
});

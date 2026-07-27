<?php

declare(strict_types=1);

use Katakata\Content\Repository;
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

$router->get('/healthz', function (Request $request): Response {
    return Response::json(['status' => 'ok']);
});

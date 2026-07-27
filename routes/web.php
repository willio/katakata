<?php

declare(strict_types=1);

use Katakata\Content\Repository;
use Katakata\Http\Request;
use Katakata\Http\Response;
use Katakata\Rendering\Markdown;
use Katakata\View;

/**
 * @var \Katakata\Http\Router $router
 * @var \Katakata\Application $app
 */

$router->get('/', function (Request $request) use ($app): Response {
    $view = $app->make(View::class);

    return Response::html($view->render('home', [
        'name' => (string) $app->config()->get('app.name', 'Katakata'),
        'tagline' => (string) $app->config()->get('app.tagline', ''),
    ]));
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

    return Response::html($app->make(View::class)->render('article', [
        'post' => $post,
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'bodyHtml' => $app->make(Markdown::class)->render($post->body),
    ]));
});

$router->get('/healthz', function (Request $request): Response {
    return Response::json(['status' => 'ok']);
});

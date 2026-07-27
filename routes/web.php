<?php

declare(strict_types=1);

use Katakata\Http\Request;
use Katakata\Http\Response;

/**
 * @var \Katakata\Http\Router $router
 * @var \Katakata\Application $app
 */

$router->get('/', function (Request $request) use ($app): Response {
    $name = htmlspecialchars((string) $app->config()->get('app.name', 'Katakata'), ENT_QUOTES);
    $tagline = htmlspecialchars((string) $app->config()->get('app.tagline', ''), ENT_QUOTES);

    return Response::html(<<<HTML
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <title>{$name}</title>
        </head>
        <body>
            <h1>{$name}</h1>
            <p>{$tagline}</p>
        </body>
        </html>
        HTML);
});

$router->get('/healthz', function (Request $request): Response {
    return Response::json(['status' => 'ok']);
});

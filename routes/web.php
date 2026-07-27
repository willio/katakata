<?php

declare(strict_types=1);

use Katakata\Http\Request;
use Katakata\Http\Response;
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

$router->get('/healthz', function (Request $request): Response {
    return Response::json(['status' => 'ok']);
});

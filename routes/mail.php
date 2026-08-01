<?php

declare(strict_types=1);

use Katakata\Http\Request;
use Katakata\Http\Response;

/** @var \Katakata\Http\Router $router */

$router->get('/dashboard/mail', fn (Request $request): Response => Response::redirect('/mail', 302));
$router->get('/dashboard/mail/search', function (Request $request): Response {
    $query = trim((string) ($request->query['q'] ?? ''));
    return Response::redirect('/mail' . ($query === '' ? '' : '?q=' . rawurlencode($query)), 302);
});

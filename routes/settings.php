<?php

declare(strict_types=1);

use Katakata\Auth\Session;
use Katakata\Dashboard\DashboardSettings;
use Katakata\Http\Request;
use Katakata\Http\Response;
use Katakata\View;

/**
 * @var \Katakata\Http\Router $router
 * @var \Katakata\Application $app
 */

$authorizeSettings = static function () use ($app): array|Response {
    $session = $app->make(Session::class);
    $user = $session->user();

    if ($user === null) {
        return Response::redirect('/login', 302);
    }

    if (!$session->canManageSettings()) {
        return Response::html('Forbidden.', 403);
    }

    return $user;
};

$router->get('/dashboard/settings', function (Request $request) use ($app, $authorizeSettings): Response {
    $authorization = $authorizeSettings();
    if ($authorization instanceof Response) {
        return $authorization;
    }

    $session = $app->make(Session::class);

    return Response::html($app->make(View::class)->render('dashboard-settings', [
        'user' => $authorization,
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'settings' => $app->make(DashboardSettings::class)->all(),
        'saved' => ($request->query['saved'] ?? '') === '1',
        'error' => null,
        'csrf' => $session->csrf(),
    ]));
});

$router->post('/dashboard/settings', function (Request $request) use ($app, $authorizeSettings): Response {
    $authorization = $authorizeSettings();
    if ($authorization instanceof Response) {
        return $authorization;
    }

    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    $section = trim((string) ($request->body['section'] ?? ''));
    try {
        $app->make(DashboardSettings::class)->update($section, $request->body);
    } catch (\Throwable $error) {
        return Response::html($app->make(View::class)->render('dashboard-settings', [
            'user' => $authorization,
            'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
            'settings' => $app->make(DashboardSettings::class)->all(),
            'saved' => false,
            'error' => $error->getMessage(),
            'csrf' => $session->csrf(),
        ]), 422);
    }

    return Response::redirect('/dashboard/settings?saved=1#' . rawurlencode($section), 303);
});

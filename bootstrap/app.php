<?php

declare(strict_types=1);

use Katakata\Application;
use Katakata\Content\Repository;
use Katakata\Http\Router;
use Katakata\Support\DotEnv;
use Katakata\View;

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/helpers.php';

// Composer's autoloader is optional: only developer tooling such as
// PHPUnit needs it. The application runs without `composer install`.
if (is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

DotEnv::load(dirname(__DIR__) . '/.env');

$app = new Application(dirname(__DIR__));
$app->boot();

$router = new Router();
$app->instance(Router::class, $router);

$app->singleton(
    Repository::class,
    static fn (Application $container): Repository => Repository::forApplication($container),
);

$app->singleton(
    View::class,
    static fn (Application $container): View => View::forApplication($container),
);

(static function () use ($router, $app): void {
    require $app->routesPath('web.php');
})();

return $app;

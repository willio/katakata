<?php

declare(strict_types=1);

use Katakata\Http\Router;

/** @var \Katakata\Application $app */
$router = $app->make(Router::class);

require $app->routesPath('web.php');
require $app->routesPath('editor.php');
require $app->routesPath('mail.php');
require $app->routesPath('article.php');

return $router;

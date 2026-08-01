<?php

declare(strict_types=1);

use Katakata\Http\Request;
use Katakata\Http\Router;

/** @var \Katakata\Application $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';

$router = require dirname(__DIR__) . '/bootstrap/routes.php';
$response = $router->dispatch(Request::fromGlobals());

$response->send();

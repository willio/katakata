<?php

declare(strict_types=1);

use Katakata\Http\Router;

/** @var \Katakata\Application $app */
require __DIR__ . '/mail.php';
require __DIR__ . '/mail-import.php';
require __DIR__ . '/settings.php';

$router = $app->make(Router::class);

require $app->routesPath('web.php');
require $app->routesPath('editor.php');
require $app->routesPath('mail-accounts.php');
require $app->routesPath('mail.php');
require $app->routesPath('campaign.php');
require $app->routesPath('settings.php');
require $app->routesPath('settings-mailbox-import.php');
require $app->routesPath('settings-mailboxes.php');
require $app->routesPath('article.php');

return $router;

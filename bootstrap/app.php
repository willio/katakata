<?php

declare(strict_types=1);

use Katakata\Application;
use Katakata\Auth\AccountStore;
use Katakata\Auth\PasskeyStore;
use Katakata\Auth\Session;
use Katakata\Auth\WebAuthn;
use Katakata\Content\Repository;
use Katakata\Editorial\AtomicFile;
use Katakata\Editorial\DraftEditor;
use Katakata\Editorial\Editor;
use Katakata\Editorial\Publisher;
use Katakata\Editorial\RevisionStore;
use Katakata\Editorial\Scheduler;
use Katakata\Distribution\Distributor;
use Katakata\Distribution\NewsletterAdapter;
use Katakata\Http\Router;
use Katakata\Rendering\Markdown;
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

$app->singleton(AtomicFile::class, static fn (): AtomicFile => new AtomicFile());
$app->singleton(
    AccountStore::class,
    static fn (Application $container): AccountStore => new AccountStore(
        $container->storagePath('auth/accounts.json'),
        $container->make(AtomicFile::class),
    ),
);
$app->singleton(
    PasskeyStore::class,
    static fn (Application $container): PasskeyStore => new PasskeyStore(
        $container->storagePath('auth/passkeys.json'),
        $container->make(AtomicFile::class),
    ),
);
$app->singleton(
    WebAuthn::class,
    static function (Application $container): WebAuthn {
        $configuredUrl = (string) $container->config()->get('app.url', 'http://localhost:8000');
        $scheme = parse_url($configuredUrl, PHP_URL_SCHEME);
        $rpId = parse_url($configuredUrl, PHP_URL_HOST);
        $port = parse_url($configuredUrl, PHP_URL_PORT);
        if (!is_string($scheme) || !is_string($rpId) || $rpId === '') {
            throw new RuntimeException('APP_URL must contain a valid origin for passkeys.');
        }
        $origin = $scheme . '://' . $rpId . (is_int($port) ? ':' . $port : '');
        return new WebAuthn(
            $container->make(PasskeyStore::class),
            $origin,
            $rpId,
            (string) $container->config()->get('app.name', 'Katakata'),
        );
    },
);
$app->singleton(
    Session::class,
    static fn (Application $container): Session => new Session($container->make(AccountStore::class)),
);
$app->singleton(
    RevisionStore::class,
    static fn (Application $container): RevisionStore => new RevisionStore(
        $container->contentPath('revisions'),
        $container->make(AtomicFile::class),
    ),
);
$app->singleton(
    DraftEditor::class,
    static fn (Application $container): DraftEditor => new DraftEditor(
        $container->basePath((string) $container->config()->get('content.drafts_path', 'content/drafts')),
        $container->make(AtomicFile::class),
        $container->make(RevisionStore::class),
    ),
);
$app->singleton(
    Publisher::class,
    static fn (Application $container): Publisher => new Publisher(
        $container->basePath((string) $container->config()->get('content.posts_path', 'content/posts')),
        $container->make(AtomicFile::class),
        $container->make(RevisionStore::class),
    ),
);
$app->singleton(
    Editor::class,
    static fn (Application $container): Editor => new Editor(
        $container->make(AtomicFile::class),
        $container->make(RevisionStore::class),
    ),
);
$app->singleton(Scheduler::class, static fn (): Scheduler => new Scheduler());
$app->singleton(
    NewsletterAdapter::class,
    static fn (Application $container): NewsletterAdapter => new NewsletterAdapter(
        $container->storagePath('distribution/newsletter'),
        (string) $container->config()->get('app.url', 'http://localhost:8000'),
        $container->make(Markdown::class),
        $container->make(AtomicFile::class),
    ),
);
$app->singleton(
    Distributor::class,
    static fn (Application $container): Distributor => new Distributor([
        $container->make(NewsletterAdapter::class),
    ]),
);

$app->singleton(
    View::class,
    static fn (Application $container): View => View::forApplication($container),
);

(static function () use ($router, $app): void {
    require $app->routesPath('web.php');
})();

return $app;

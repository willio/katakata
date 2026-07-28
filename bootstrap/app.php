<?php

declare(strict_types=1);

use Katakata\Application;
use Katakata\Analytics\AnalyticsStore;
use Katakata\Analytics\VisitorHasher;
use Katakata\Analytics\VisitRecorder;
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
use Katakata\Distribution\ConfirmationMailer;
use Katakata\Distribution\Distributor;
use Katakata\Distribution\EmailTransport;
use Katakata\Distribution\FilesystemEmailTransport;
use Katakata\Distribution\MailQueue;
use Katakata\Dashboard\DashboardAnalytics;
use Katakata\Distribution\NewsletterAdapter;
use Katakata\Distribution\NewsletterDispatcher;
use Katakata\Distribution\SubscriberStore;
use Katakata\Http\Router;
use Katakata\Rendering\Markdown;
use Katakata\Seo\SeoChecker;
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
    AnalyticsStore::class,
    static fn (Application $container): AnalyticsStore => new AnalyticsStore(
        $container->storagePath('analytics/analytics.sqlite'),
    ),
);
$app->singleton(
    VisitorHasher::class,
    static fn (Application $container): VisitorHasher => new VisitorHasher(
        (string) $container->config()->get('analytics.secret', ''),
    ),
);
$app->singleton(
    VisitRecorder::class,
    static fn (Application $container): VisitRecorder => new VisitRecorder(
        $container->make(AnalyticsStore::class),
        $container->make(VisitorHasher::class),
    ),
);

$app->singleton(
    Repository::class,
    static fn (Application $container): Repository => Repository::forApplication($container),
);

$app->singleton(
    DashboardAnalytics::class,
    static fn (Application $container): DashboardAnalytics => new DashboardAnalytics(
        $container->make(AnalyticsStore::class),
        $container->make(Repository::class),
    ),
);
$app->singleton(
    SeoChecker::class,
    static fn (Application $container): SeoChecker => new SeoChecker(
        $container->make(Repository::class),
        $container->basePath('public'),
    ),
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
    EmailTransport::class,
    static function (Application $container): EmailTransport {
        $transport = strtolower((string) $container->config()->get('mail.transport', 'filesystem'));
        if ($transport === 'resend') {
            return new ResendEmailTransport(
                (string) $container->config()->get('mail.resend_key', ''),
                (string) $container->config()->get('mail.from', ''),
            );
        }
        if ($transport !== 'filesystem') {
            throw new RuntimeException("Unsupported mail transport [{$transport}].");
        }
        return new FilesystemEmailTransport(
            $container->storagePath('distribution/mail/sent'),
            $container->make(AtomicFile::class),
        );
    },
);
$app->singleton(
    MailQueue::class,
    static fn (Application $container): MailQueue => new MailQueue(
        $container->storagePath('distribution/mail/queue'),
        $container->make(EmailTransport::class),
        $container->make(AtomicFile::class),
    ),
);
$app->singleton(
    ConfirmationMailer::class,
    static fn (Application $container): ConfirmationMailer => new ConfirmationMailer(
        $container->make(MailQueue::class),
        (string) $container->config()->get('app.url', 'http://localhost:8000'),
        (string) $container->config()->get('app.name', 'Katakata'),
    ),
);
$app->singleton(
    NewsletterDispatcher::class,
    static fn (Application $container): NewsletterDispatcher => new NewsletterDispatcher(
        $container->make(NewsletterAdapter::class),
        $container->make(SubscriberStore::class),
        $container->make(MailQueue::class),
        (string) $container->config()->get('app.url', 'http://localhost:8000'),
    ),
);
$app->singleton(
    SubscriberStore::class,
    static fn (Application $container): SubscriberStore => new SubscriberStore(
        $container->storagePath('distribution/subscribers.json'),
        (string) $container->config()->get('newsletter.secret', ''),
        $container->make(AtomicFile::class),
    ),
);
$app->singleton(
    ThreadsStore::class,
    static fn (Application $container): ThreadsStore => new ThreadsStore(
        $container->storagePath('distribution/threads.json'),
        $container->make(AtomicFile::class),
    ),
);
$app->singleton(
    ThreadsApi::class,
    static fn (Application $container): ThreadsApi => new MetaThreadsApi(
        (string) $container->config()->get('threads.user_id', ''),
        (string) $container->config()->get('threads.access_token', ''),
    ),
);
$app->singleton(
    ThreadsAdapter::class,
    static fn (Application $container): ThreadsAdapter => new ThreadsAdapter(
        $container->make(ThreadsApi::class),
        $container->make(ThreadsStore::class),
        (string) $container->config()->get('app.url', 'http://localhost:8000'),
    ),
);
$app->singleton(
    ThreadsReplySync::class,
    static fn (Application $container): ThreadsReplySync => new ThreadsReplySync(
        $container->make(ThreadsApi::class),
        $container->make(ThreadsStore::class),
    ),
);
$app->singleton(
    Distributor::class,
    static function (Application $container): Distributor {
        $adapters = [$container->make(NewsletterAdapter::class)];
        if ((bool) $container->config()->get('threads.enabled', false)) {
            $adapters[] = $container->make(ThreadsAdapter::class);
        }
        return new Distributor($adapters);
    },
);

$app->singleton(
    View::class,
    static fn (Application $container): View => View::forApplication($container),
);

(static function () use ($router, $app): void {
    require $app->routesPath('web.php');
})();

return $app;

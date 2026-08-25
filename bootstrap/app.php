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
use Katakata\Editorial\ContentTrash;
use Katakata\Editorial\Editor;
use Katakata\Editorial\Publisher;
use Katakata\Editorial\RevisionStore;
use Katakata\Editorial\Scheduler;
use Katakata\Distribution\ConfirmationMailer;
use Katakata\Distribution\Distributor;
use Katakata\Distribution\EmailTransport;
use Katakata\Distribution\EmailTransportRegistry;
use Katakata\Distribution\FilesystemEmailTransport;
use Katakata\Distribution\MailQueue;
use Katakata\Dashboard\DashboardAnalytics;
use Katakata\Dashboard\DashboardBuzz;
use Katakata\Dashboard\DashboardSettings;
use Katakata\Discussion\DiscussionManager;
use Katakata\Discussion\NativeDiscussionProvider;
use Katakata\Discussion\NativeDiscussionService;
use Katakata\Discussion\NativeDiscussionStore;
use Katakata\Discussion\Providers\NullDiscussionProvider;
use Katakata\Discussion\Providers\ThreadsDiscussionProvider;
use Katakata\Distribution\NewsletterAdapter;
use Katakata\Distribution\NewsletterDispatcher;
use Katakata\Distribution\MetaThreadsApi;
use Katakata\Distribution\ResendEmailTransport;
use Katakata\Distribution\ResendWebhook;
use Katakata\Distribution\ThreadsAdapter;
use Katakata\Distribution\ThreadsApi;
use Katakata\Distribution\ThreadsEngagementSync;
use Katakata\Distribution\ThreadsInsightsApi;
use Katakata\Distribution\ThreadsReplySync;
use Katakata\Distribution\ThreadsStore;
use Katakata\Distribution\SubscriberStore;
use Katakata\Distribution\UnavailableSubscriberStore;
use Katakata\Http\Router;
use Katakata\Import\DirectoryDocumentImporter;
use Katakata\Import\DocxDocumentParser;
use Katakata\Import\KatakataDocumentWriter;
use Katakata\Import\LegacyDocConverter;
use Katakata\Import\LegacyDocumentImporter;
use Katakata\Rendering\Markdown;
use Katakata\Seo\SeoChecker;
use Katakata\Settings\SecretsStore;
use Katakata\Support\DotEnv;
use Katakata\View;

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/helpers.php';

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
    DashboardBuzz::class,
    static function (Application $container): DashboardBuzz {
        $discussion = $container->make(DashboardSettings::class)->section('discussion');

        return new DashboardBuzz(
            $container->make(DiscussionManager::class),
            (string) ($discussion['provider'] ?? 'none'),
        );
    },
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
    ContentTrash::class,
    static fn (Application $container): ContentTrash => new ContentTrash(
        $container->contentPath(),
        $container->make(AtomicFile::class),
        $container->make(RevisionStore::class),
    ),
);
$app->singleton(DocxDocumentParser::class, static fn (): DocxDocumentParser => new DocxDocumentParser());
$app->singleton(
    KatakataDocumentWriter::class,
    static fn (Application $container): KatakataDocumentWriter => new KatakataDocumentWriter(
        $container->make(DraftEditor::class),
        $container->basePath((string) $container->config()->get('content.drafts_path', 'content/drafts')),
    ),
);
$app->singleton(
    LegacyDocConverter::class,
    static fn (Application $container): LegacyDocConverter => new LegacyDocConverter(
        $container->storagePath('tmp/import'),
    ),
);
$app->singleton(
    LegacyDocumentImporter::class,
    static fn (Application $container): LegacyDocumentImporter => new LegacyDocumentImporter(
        $container->make(DocxDocumentParser::class),
        $container->make(KatakataDocumentWriter::class),
        $container->make(LegacyDocConverter::class),
    ),
);
$app->singleton(
    DirectoryDocumentImporter::class,
    static fn (Application $container): DirectoryDocumentImporter => new DirectoryDocumentImporter(
        $container->make(LegacyDocumentImporter::class),
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
    EmailTransportRegistry::class,
    static function (Application $container): EmailTransportRegistry {
        return (new EmailTransportRegistry())
            ->register(
                'filesystem',
                static fn (): EmailTransport => new FilesystemEmailTransport(
                    $container->storagePath('distribution/mail/sent'),
                    $container->make(AtomicFile::class),
                ),
            )
            ->register(
                'resend',
                static fn (): EmailTransport => new ResendEmailTransport(
                    (string) $container->config()->get('mail.resend_key', ''),
                    (string) $container->config()->get('mail.from', ''),
                ),
            );
    },
);
$app->singleton(
    EmailTransport::class,
    static fn (Application $container): EmailTransport => $container
        ->make(EmailTransportRegistry::class)
        ->resolve((string) $container->config()->get('mail.transport', 'filesystem')),
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
    SubscriberStore::class,
    static function (Application $container): SubscriberStore {
        $secret = trim((string) $container->config()->get('newsletter.secret', ''));
        if ($secret === '') {
            return new UnavailableSubscriberStore();
        }

        return new SubscriberStore(
            $container->storagePath('distribution/subscribers.json'),
            $secret,
            $container->make(AtomicFile::class),
        );
    },
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
    ResendWebhook::class,
    static fn (Application $container): ResendWebhook => new ResendWebhook(
        $container->storagePath('distribution/resend/webhooks'),
        (string) $container->config()->get('mail.resend_webhook_secret', ''),
        $container->make(SubscriberStore::class),
        $container->make(AtomicFile::class),
    ),
);
// Settings bindings must exist before Threads wiring resolves them; routes.php
// re-requires this file harmlessly when composing the HTTP routes.
require __DIR__ . '/settings.php';

// Effective Threads credentials: dashboard settings win over deployment config.
// The access token prefers the encrypted application secret store, then the
// environment variable named by settings, then deployment config, so token
// values never touch settings storage.
$threadsCredentials = static function (Application $container): array {
    $discussion = $container->make(DashboardSettings::class)->section('discussion');
    $userId = trim((string) ($discussion['threads_user_id'] ?? ''));
    if ($userId === '') {
        $userId = trim((string) $container->config()->get('threads.user_id', ''));
    }
    $token = '';
    $secrets = $container->make(SecretsStore::class);
    if ($secrets->available() && $secrets->has('threads.access_token')) {
        $token = trim((string) $secrets->get('threads.access_token'));
    }
    if ($token === '') {
        $secretName = trim((string) ($discussion['threads_token_secret'] ?? ''));
        if ($secretName === '' || preg_match('/^[A-Z][A-Z0-9_]*$/', $secretName) !== 1) {
            $secretName = 'THREADS_ACCESS_TOKEN';
        }
        $envToken = getenv($secretName);
        $token = is_string($envToken) ? trim($envToken) : '';
    }
    if ($token === '') {
        $token = trim((string) $container->config()->get('threads.access_token', ''));
    }

    return [$userId, $token];
};

// Effective discussion provider selection: the stored dashboard setting wins,
// and its default already reflects THREADS_ENABLED, so selecting Threads in
// Settings activates the provider without any environment flag.
$threadsSelected = static fn (Application $container): bool => trim((string) (
    $container->make(DashboardSettings::class)->section('discussion')['provider'] ?? 'none'
)) === 'threads';

$app->singleton(
    ThreadsStore::class,
    static fn (Application $container): ThreadsStore => new ThreadsStore(
        $container->storagePath('distribution/threads.json'),
        $container->make(AtomicFile::class),
    ),
);
$app->singleton(
    ThreadsApi::class,
    static function (Application $container) use ($threadsCredentials): ThreadsApi {
        [$userId, $token] = $threadsCredentials($container);

        return new MetaThreadsApi($userId, $token);
    },
);
$app->singleton(
    ThreadsInsightsApi::class,
    static fn (Application $container): ThreadsInsightsApi => $container->make(ThreadsApi::class),
);
$app->singleton(
    ThreadsDiscussionProvider::class,
    static fn (Application $container): ThreadsDiscussionProvider => new ThreadsDiscussionProvider(
        $container->make(ThreadsApi::class),
        $container->make(ThreadsStore::class),
        $threadsSelected($container),
    ),
);
$app->singleton(
    NativeDiscussionStore::class,
    static fn (Application $container): NativeDiscussionStore => new NativeDiscussionStore(
        $container->storagePath('discussion/native'),
        $container->make(AtomicFile::class),
    ),
);
$app->singleton(
    NativeDiscussionProvider::class,
    static fn (Application $container): NativeDiscussionProvider => new NativeDiscussionProvider(
        $container->make(NativeDiscussionStore::class),
    ),
);
$app->singleton(
    NativeDiscussionService::class,
    static fn (Application $container): NativeDiscussionService => new NativeDiscussionService(
        $container->make(NativeDiscussionProvider::class),
        $container->make(NativeDiscussionStore::class),
    ),
);
$app->singleton(
    DiscussionManager::class,
    static function (Application $container) use ($threadsCredentials, $threadsSelected): DiscussionManager {
        $providers = [$container->make(NativeDiscussionProvider::class)];
        if (!$threadsSelected($container)) {
            return new DiscussionManager(new NullDiscussionProvider(), ...$providers);
        }

        [$threadsUserId, $threadsToken] = $threadsCredentials($container);
        $threadsAvailable = $threadsUserId !== ''
            && $threadsToken !== '';
        if ($threadsAvailable) {
            $providers[] = $container->make(ThreadsDiscussionProvider::class);
        }

        return new DiscussionManager(new NullDiscussionProvider(), ...$providers);
    },
);
$app->singleton(
    \Katakata\Discussion\PublicDiscussion::class,
    static function (Application $container): \Katakata\Discussion\PublicDiscussion {
        $settings = $container->make(DashboardSettings::class)->section('discussion');

        return new \Katakata\Discussion\PublicDiscussion(
            $container->make(DiscussionManager::class),
            (string) ($settings['provider'] ?? 'none'),
            (bool) ($settings['enabled_by_default'] ?? false),
        );
    },
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
    ThreadsEngagementSync::class,
    static fn (Application $container): ThreadsEngagementSync => new ThreadsEngagementSync(
        $container->make(ThreadsInsightsApi::class),
        $container->make(ThreadsStore::class),
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

return $app;

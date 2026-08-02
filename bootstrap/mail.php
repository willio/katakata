<?php

declare(strict_types=1);

use Katakata\Application;
use Katakata\Content\Repository;
use Katakata\Distribution\MailQueue;
use Katakata\Distribution\SubscriberStore;
use Katakata\Editorial\AtomicFile;
use Katakata\Email\DraftComposer;
use Katakata\Email\DraftSender;
use Katakata\Email\DraftStore;
use Katakata\Email\FileDraftStore;
use Katakata\Email\ImapSettings;
use Katakata\Email\Mailbox;
use Katakata\Email\MailboxProvider;
use Katakata\Email\OutboundMailProvider;
use Katakata\Email\Providers\CachedMailboxProvider;
use Katakata\Email\Providers\UnavailableOutboundMailProvider;
use Katakata\Mail\CampaignDispatcher;
use Katakata\Mail\CampaignDraftFactory;
use Katakata\Mail\CampaignDraftReviewer;
use Katakata\Mail\CampaignDraftStore;
use Katakata\Mail\CampaignRetryService;
use Katakata\Mail\CampaignStatus;
use Katakata\Mail\CampaignStore;
use Katakata\Mail\MailAttention;
use Katakata\Mail\MailWorkspace;
use Katakata\Rendering\Markdown;

/** @var Application $app */

$app->singleton(ImapSettings::class, static fn (): ImapSettings => ImapSettings::fromEnvironment());
$app->singleton(
    MailboxProvider::class,
    static fn (Application $container): MailboxProvider => new CachedMailboxProvider(
        $container->storagePath('mail/cache'),
        $container->make(AtomicFile::class),
    ),
);
$app->singleton(
    Mailbox::class,
    static fn (Application $container): Mailbox => new Mailbox($container->make(MailboxProvider::class)),
);
$app->singleton(
    DraftStore::class,
    static fn (Application $container): DraftStore => new FileDraftStore(
        $container->storagePath('mail/drafts'),
        $container->make(AtomicFile::class),
    ),
);
$app->singleton(
    DraftComposer::class,
    static fn (Application $container): DraftComposer => new DraftComposer($container->make(DraftStore::class)),
);
$app->singleton(
    OutboundMailProvider::class,
    static fn (): OutboundMailProvider => new UnavailableOutboundMailProvider(),
);
$app->singleton(
    DraftSender::class,
    static fn (Application $container): DraftSender => new DraftSender(
        $container->make(DraftStore::class),
        $container->make(OutboundMailProvider::class),
    ),
);
$app->singleton(
    CampaignDraftStore::class,
    static fn (Application $container): CampaignDraftStore => new CampaignDraftStore(
        $container->storagePath('mail/campaign-drafts'),
        $container->make(AtomicFile::class),
    ),
);
$app->singleton(CampaignDraftFactory::class, static fn (): CampaignDraftFactory => new CampaignDraftFactory());
$app->singleton(
    CampaignDraftReviewer::class,
    static fn (Application $container): CampaignDraftReviewer => new CampaignDraftReviewer(
        $container->make(SubscriberStore::class),
        $container->make(Markdown::class),
    ),
);
$app->singleton(
    MailWorkspace::class,
    static fn (Application $container): MailWorkspace => new MailWorkspace(
        $container->make(Repository::class),
        $container->make(SubscriberStore::class),
        $container->make(Markdown::class),
        (string) $container->config()->get('app.url', 'http://localhost:8000'),
    ),
);
$app->singleton(
    CampaignStore::class,
    static fn (Application $container): CampaignStore => new CampaignStore(
        $container->storagePath('distribution/mail/campaigns'),
        $container->make(AtomicFile::class),
    ),
);
$app->singleton(
    CampaignStatus::class,
    static fn (Application $container): CampaignStatus => new CampaignStatus(
        $container->storagePath('distribution/mail/queue'),
    ),
);
$app->singleton(
    MailAttention::class,
    static fn (Application $container): MailAttention => new MailAttention(
        $container->make(Mailbox::class),
        $container->make(CampaignStore::class),
        $container->make(CampaignStatus::class),
    ),
);
$app->singleton(
    CampaignRetryService::class,
    static fn (Application $container): CampaignRetryService => new CampaignRetryService(
        $container->storagePath('distribution/mail/queue'),
        $container->make(AtomicFile::class),
    ),
);
$app->singleton(
    CampaignDispatcher::class,
    static fn (Application $container): CampaignDispatcher => new CampaignDispatcher(
        $container->make(MailWorkspace::class),
        $container->make(SubscriberStore::class),
        $container->make(CampaignStore::class),
        $container->make(MailQueue::class),
        (string) $container->config()->get('app.url', 'http://localhost:8000'),
    ),
);

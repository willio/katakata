<?php

declare(strict_types=1);

use Katakata\Application;
use Katakata\Content\Repository;
use Katakata\Distribution\MailQueue;
use Katakata\Distribution\SubscriberStore;
use Katakata\Editorial\AtomicFile;
use Katakata\Mail\CampaignDispatcher;
use Katakata\Mail\CampaignRetryService;
use Katakata\Mail\CampaignStatus;
use Katakata\Mail\CampaignStore;
use Katakata\Mail\MailWorkspace;
use Katakata\Rendering\Markdown;

/** @var Application $app */

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

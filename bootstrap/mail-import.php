<?php

declare(strict_types=1);

use Katakata\Application;
use Katakata\Editorial\AtomicFile;
use Katakata\Email\Import\MailProfileImportStore;
use Katakata\Email\Import\MobileconfigAccountImporter;
use Katakata\Email\Import\SafePlistParser;

/** @var Application $app */

$app->singleton(SafePlistParser::class, static fn (): SafePlistParser => new SafePlistParser());
$app->singleton(
    MobileconfigAccountImporter::class,
    static fn (Application $container): MobileconfigAccountImporter => new MobileconfigAccountImporter(
        $container->make(SafePlistParser::class),
    ),
);
$app->singleton(
    MailProfileImportStore::class,
    static fn (Application $container): MailProfileImportStore => new MailProfileImportStore(
        $container->storagePath('mail/imports'),
        $container->make(AtomicFile::class),
    ),
);

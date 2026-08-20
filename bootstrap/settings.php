<?php

declare(strict_types=1);

use Katakata\Application;
use Katakata\Dashboard\DashboardSettings;
use Katakata\Editorial\AtomicFile;
use Katakata\Settings\SettingsStore;

/** @var Application $app */

$threadsConfigured = trim((string) $app->config()->get('threads.user_id', '')) !== ''
    && trim((string) $app->config()->get('threads.access_token', '')) !== '';

$app->singleton(
    SettingsStore::class,
    static fn (Application $container): SettingsStore => new SettingsStore(
        $container->storagePath('settings/application.json'),
        $container->make(AtomicFile::class),
    ),
);

$app->singleton(
    DashboardSettings::class,
    static fn (Application $container): DashboardSettings => new DashboardSettings(
        $container->make(SettingsStore::class),
        [
            'publication' => [
                'name' => (string) $container->config()->get('app.name', 'Katakata'),
                'description' => '',
                'default_author' => '',
            ],
            'newsletter' => [
                'sender_label' => (string) $container->config()->get('mail.from_name', 'Katakata'),
                'publish_by_default' => false,
            ],
            'discussion' => [
                'provider' => (bool) $container->config()->get('threads.enabled', false) && $threadsConfigured
                    ? 'threads'
                    : 'none',
                'enabled_by_default' => false,
            ],
            'analytics' => ['dashboard_period' => '30d'],
            'appearance' => ['theme' => 'default', 'button_style' => 'regular'],
        ],
        $threadsConfigured,
    ),
);

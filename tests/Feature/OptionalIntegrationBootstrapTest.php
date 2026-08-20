<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use Katakata\Discussion\DiscussionManager;
use Katakata\Dashboard\DashboardBuzz;
use Katakata\Dashboard\DashboardSettings;
use Katakata\Distribution\EmailTransport;
use Katakata\Distribution\FilesystemEmailTransport;
use Katakata\Editorial\AtomicFile;
use Katakata\Settings\SettingsStore;
use PHPUnit\Framework\TestCase;

final class OptionalIntegrationBootstrapTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $environment = [];

    protected function tearDown(): void
    {
        foreach ($this->environment as $key => $value) {
            if ($value === false) {
                unset($_ENV[$key]);
                putenv($key);
                continue;
            }

            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    public function testDisabledThreadsResolvesTheNullProviderWithoutCredentials(): void
    {
        $this->environment([
            'THREADS_ENABLED' => 'false',
            'THREADS_USER_ID' => '',
            'THREADS_ACCESS_TOKEN' => '',
        ]);

        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $manager = $app->make(DiscussionManager::class);

        self::assertSame('none', $manager->resolve('threads')->key());
    }

    public function testSettingsSuppliedThreadsIdentityActivatesTheProvider(): void
    {
        $this->environment([
            'THREADS_ENABLED' => 'true',
            'THREADS_USER_ID' => '',
            'THREADS_ACCESS_TOKEN' => '',
            'KATAKATA_TEST_THREADS_TOKEN' => 'settings-referenced-token',
        ]);

        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $app->instance(DashboardSettings::class, new DashboardSettings(
            new SettingsStore(
                sys_get_temp_dir() . '/katakata-missing-settings-' . bin2hex(random_bytes(6)) . '/application.json',
                new AtomicFile(),
            ),
            ['discussion' => [
                'provider' => 'threads',
                'enabled_by_default' => false,
                'threads_user_id' => 'settings-user',
                'threads_token_secret' => 'KATAKATA_TEST_THREADS_TOKEN',
            ]],
        ));
        $manager = $app->make(DiscussionManager::class);

        self::assertSame('threads', $manager->resolve('threads')->key());
    }

    public function testEnabledThreadsStaysInertWithoutAnyCredentials(): void
    {
        $this->environment([
            'THREADS_ENABLED' => 'true',
            'THREADS_USER_ID' => '',
            'THREADS_ACCESS_TOKEN' => '',
            'KATAKATA_TEST_UNSET_THREADS_TOKEN' => '',
        ]);

        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $app->instance(DashboardSettings::class, new DashboardSettings(
            new SettingsStore(
                sys_get_temp_dir() . '/katakata-missing-settings-' . bin2hex(random_bytes(6)) . '/application.json',
                new AtomicFile(),
            ),
            ['discussion' => [
                'provider' => 'threads',
                'enabled_by_default' => false,
                'threads_user_id' => '',
                'threads_token_secret' => 'KATAKATA_TEST_UNSET_THREADS_TOKEN',
            ]],
        ));
        $manager = $app->make(DiscussionManager::class);

        self::assertSame('none', $manager->resolve('threads')->key());
    }

    public function testFilesystemMailDoesNotResolveResendCredentials(): void
    {
        $this->environment([
            'MAIL_TRANSPORT' => 'filesystem',
            'RESEND_API_KEY' => '',
        ]);

        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';

        self::assertInstanceOf(FilesystemEmailTransport::class, $app->make(EmailTransport::class));
    }

    public function testDashboardBuzzUsesTheGlobalDiscussionProviderSelection(): void
    {
        $this->environment([
            'THREADS_ENABLED' => 'false',
            'THREADS_USER_ID' => '',
            'THREADS_ACCESS_TOKEN' => '',
        ]);

        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $app->instance(DashboardSettings::class, new DashboardSettings(
            new SettingsStore(
                sys_get_temp_dir() . '/katakata-missing-settings-' . bin2hex(random_bytes(6)) . '/application.json',
                new AtomicFile(),
            ),
            ['discussion' => ['provider' => 'native', 'enabled_by_default' => false]],
        ));

        self::assertSame([], $app->make(DashboardBuzz::class)->recent());
    }

    /** @param array<string, string> $values */
    private function environment(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->environment[$key] = getenv($key);
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

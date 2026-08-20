<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Settings;

use Katakata\Dashboard\DashboardSettings;
use Katakata\Editorial\AtomicFile;
use Katakata\Settings\SecretsStore;
use Katakata\Settings\SettingsStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DashboardSettingsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-dashboard-settings-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (is_file($this->root . '/application.json')) {
            unlink($this->root . '/application.json');
        }
        if (is_dir($this->root)) {
            rmdir($this->root);
        }
    }

    public function testItMergesStoredValuesOverDefaultsWithoutWritingOnRead(): void
    {
        $settings = $this->settings();

        self::assertSame('Katakata', $settings->section('publication')['name']);
        self::assertFileDoesNotExist($this->root . '/application.json');

        $settings->update('publication', [
            'name' => 'New name',
            'description' => 'Independent publishing.',
            'default_author' => 'will',
        ]);

        self::assertSame('New name', $settings->section('publication')['name']);
        self::assertSame('Independent publishing.', $settings->section('publication')['description']);
    }

    public function testItRejectsInvalidValuesWithoutChangingAnotherSection(): void
    {
        $settings = $this->settings();
        $settings->update('newsletter', [
            'sender_label' => 'Letter',
            'publish_by_default' => true,
        ]);

        try {
            $settings->update('discussion', ['provider' => 'unknown']);
            self::fail('Expected invalid provider to fail.');
        } catch (RuntimeException $error) {
            self::assertSame('Discussion provider is invalid.', $error->getMessage());
        }

        self::assertSame([
            'sender_label' => 'Letter',
            'publish_by_default' => true,
        ], $settings->section('newsletter'));
        self::assertSame('none', $settings->section('discussion')['provider']);
    }

    public function testItNormalizesCheckboxValuesAndValidatesEnumerations(): void
    {
        $settings = $this->settings();

        self::assertSame([
            'provider' => 'native',
            'enabled_by_default' => true,
            'threads_user_id' => '',
            'threads_token_secret' => 'THREADS_ACCESS_TOKEN',
        ], $settings->update('discussion', [
            'provider' => 'native',
            'enabled_by_default' => '1',
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Appearance theme is invalid.');
        $settings->update('appearance', ['theme' => 'neon']);
    }

    public function testItPersistsValidAppearanceButtonStyles(): void
    {
        $settings = $this->settings();

        self::assertSame([
            'theme' => 'default',
            'button_style' => 'pill',
        ], $settings->update('appearance', [
            'theme' => 'default',
            'button_style' => 'pill',
        ]));
        self::assertSame('pill', $settings->section('appearance')['button_style']);

        $settings->update('appearance', [
            'theme' => 'default',
            'button_style' => 'regular',
        ]);
        self::assertSame('regular', $settings->section('appearance')['button_style']);
    }

    public function testItRejectsAnInvalidAppearanceButtonStyle(): void
    {
        $settings = $this->settings();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Appearance button style is invalid.');
        $settings->update('appearance', ['button_style' => 'square']);
    }

    public function testAppearanceButtonStyleDefaultsToRegular(): void
    {
        $settings = $this->settings();

        self::assertSame([
            'theme' => 'warm',
            'button_style' => 'regular',
        ], $settings->update('appearance', ['theme' => 'warm']));
    }

    public function testAppearancePreservesTheStoredThemeWhenItIsOmitted(): void
    {
        $settings = $this->settings();
        $settings->update('appearance', [
            'theme' => 'warm',
            'button_style' => 'regular',
        ]);

        self::assertSame([
            'theme' => 'warm',
            'button_style' => 'pill',
        ], $settings->update('appearance', ['button_style' => 'pill']));
        self::assertSame('warm', $settings->section('appearance')['theme']);
    }

    public function testThreadsCannotBeEnabledWithoutDeploymentCredentials(): void
    {
        $settings = $this->settings();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Threads requires THREADS_USER_ID and THREADS_ACCESS_TOKEN.');
        $settings->update('discussion', [
            'provider' => 'threads',
            'enabled_by_default' => true,
        ]);
    }

    public function testThreadsCanBeSelectedWhenDeploymentCredentialsAreAvailable(): void
    {
        $settings = $this->settings(threadsConfigured: true);

        self::assertSame([
            'provider' => 'threads',
            'enabled_by_default' => true,
            'threads_user_id' => '',
            'threads_token_secret' => 'THREADS_ACCESS_TOKEN',
        ], $settings->update('discussion', [
            'provider' => 'threads',
            'enabled_by_default' => '1',
        ]));
    }

    public function testDiscussionPersistsTheThreadsIdentitySettings(): void
    {
        $settings = $this->settings();

        self::assertSame([
            'provider' => 'native',
            'enabled_by_default' => false,
            'threads_user_id' => '12345',
            'threads_token_secret' => 'KATAKATA_TEST_THREADS_TOKEN',
        ], $settings->update('discussion', [
            'provider' => 'native',
            'threads_user_id' => ' 12345 ',
            'threads_token_secret' => 'KATAKATA_TEST_THREADS_TOKEN',
        ]));
        self::assertSame('12345', $settings->section('discussion')['threads_user_id']);
        self::assertSame(
            'KATAKATA_TEST_THREADS_TOKEN',
            $settings->section('discussion')['threads_token_secret'],
        );
    }

    public function testDiscussionRejectsInvalidThreadsTokenSecretNames(): void
    {
        $settings = $this->settings();

        foreach (['threads_token', 'THREADS-TOKEN'] as $name) {
            try {
                $settings->update('discussion', [
                    'provider' => 'none',
                    'threads_token_secret' => $name,
                ]);
                self::fail("Expected token secret name [{$name}] to fail.");
            } catch (RuntimeException $error) {
                self::assertSame('Threads token secret name is invalid.', $error->getMessage());
            }
        }

        try {
            $settings->update('discussion', [
                'provider' => 'threads',
                'threads_token_secret' => '',
            ]);
            self::fail('Expected an empty token secret name to fail.');
        } catch (RuntimeException $error) {
            self::assertSame('Threads token secret name is invalid.', $error->getMessage());
        }
    }

    public function testThreadsCanBeSelectedWhenSettingsSupplyCredentials(): void
    {
        putenv('KATAKATA_TEST_THREADS_TOKEN=fake-unit-test-token');
        try {
            $settings = $this->settings();

            self::assertSame([
                'provider' => 'threads',
                'enabled_by_default' => false,
                'threads_user_id' => '12345',
                'threads_token_secret' => 'KATAKATA_TEST_THREADS_TOKEN',
            ], $settings->update('discussion', [
                'provider' => 'threads',
                'threads_user_id' => '12345',
                'threads_token_secret' => 'KATAKATA_TEST_THREADS_TOKEN',
            ]));
        } finally {
            putenv('KATAKATA_TEST_THREADS_TOKEN');
        }
    }

    public function testThreadsCanBeEnabledLaterUsingStoredCredentials(): void
    {
        putenv('KATAKATA_TEST_THREADS_TOKEN=fake-unit-test-token');
        try {
            $settings = $this->settings();
            $settings->update('discussion', [
                'provider' => 'native',
                'threads_user_id' => '12345',
                'threads_token_secret' => 'KATAKATA_TEST_THREADS_TOKEN',
            ]);

            self::assertSame('threads', $settings->update('discussion', [
                'provider' => 'threads',
            ])['provider']);
        } finally {
            putenv('KATAKATA_TEST_THREADS_TOKEN');
        }
    }

    public function testThreadsIsRejectedWhenOnlyTheUserIdIsSupplied(): void
    {
        putenv('KATAKATA_TEST_MISSING_THREADS_TOKEN');
        $settings = $this->settings();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Threads requires THREADS_USER_ID and THREADS_ACCESS_TOKEN.');
        $settings->update('discussion', [
            'provider' => 'threads',
            'threads_user_id' => '12345',
            'threads_token_secret' => 'KATAKATA_TEST_MISSING_THREADS_TOKEN',
        ]);
    }

    public function testThreadsCanBeSelectedWhenTheSecretsStoreHoldsTheToken(): void
    {
        putenv('KATAKATA_TEST_MISSING_THREADS_TOKEN');
        $secrets = new SecretsStore($this->root . '/secrets.json', new AtomicFile(), 'unit-test-app-key');
        $secrets->set('threads.access_token', 'encrypted-unit-test-token');
        $settings = $this->settings(secrets: $secrets);

        self::assertSame('threads', $settings->update('discussion', [
            'provider' => 'threads',
            'threads_user_id' => '12345',
        ])['provider']);
    }

    private function settings(bool $threadsConfigured = false, ?SecretsStore $secrets = null): DashboardSettings
    {
        return new DashboardSettings(
            new SettingsStore($this->root . '/application.json', new AtomicFile()),
            [
                'publication' => [
                    'name' => 'Katakata',
                    'description' => '',
                    'default_author' => '',
                ],
                'newsletter' => [
                    'sender_label' => 'Letter',
                    'publish_by_default' => false,
                ],
                'discussion' => [
                    'provider' => 'none',
                    'enabled_by_default' => false,
                    'threads_user_id' => '',
                    'threads_token_secret' => 'THREADS_ACCESS_TOKEN',
                ],
                'analytics' => ['dashboard_period' => '30d'],
                'appearance' => ['theme' => 'default', 'button_style' => 'regular'],
            ],
            $threadsConfigured,
            $secrets,
        );
    }
}

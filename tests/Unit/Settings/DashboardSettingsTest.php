<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Settings;

use Katakata\Dashboard\DashboardSettings;
use Katakata\Editorial\AtomicFile;
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
        ], $settings->update('discussion', [
            'provider' => 'native',
            'enabled_by_default' => '1',
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Appearance theme is invalid.');
        $settings->update('appearance', ['theme' => 'neon']);
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
        ], $settings->update('discussion', [
            'provider' => 'threads',
            'enabled_by_default' => '1',
        ]));
    }

    private function settings(bool $threadsConfigured = false): DashboardSettings
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
                ],
                'analytics' => ['dashboard_period' => '30d'],
                'appearance' => ['theme' => 'default'],
            ],
            $threadsConfigured,
        );
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use Katakata\Support\DotEnv;
use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase
{
    private const KEYS = ['APP_KEY', 'NEWSLETTER_SECRET', 'ANALYTICS_SECRET', 'KATAKATA_TEST_ENV'];

    /** @var array<string, string|false> */
    private array $original = [];

    protected function setUp(): void
    {
        foreach (self::KEYS as $key) {
            $this->original[$key] = getenv($key);
            unset($_ENV[$key]);
            putenv($key);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->original as $key => $value) {
            unset($_ENV[$key]);
            $value === false ? putenv($key) : putenv("{$key}={$value}");
        }
    }

    public function testMissingVariableReturnsDefault(): void
    {
        self::assertSame('fallback', env('KATAKATA_TEST_ENV', 'fallback'));
        self::assertNull(env('KATAKATA_TEST_ENV'));
    }

    public function testSetVariableIsReturnedWithScalarCoercion(): void
    {
        putenv('KATAKATA_TEST_ENV=value');
        self::assertSame('value', env('KATAKATA_TEST_ENV', 'fallback'));

        putenv('KATAKATA_TEST_ENV=true');
        self::assertTrue(env('KATAKATA_TEST_ENV', false));

        putenv('KATAKATA_TEST_ENV=false');
        self::assertFalse(env('KATAKATA_TEST_ENV', true));

        putenv('KATAKATA_TEST_ENV=null');
        self::assertNull(env('KATAKATA_TEST_ENV', 'fallback'));
    }

    public function testEmptyVariableFromEnvArrayCountsAsUnset(): void
    {
        $_ENV['KATAKATA_TEST_ENV'] = '';

        self::assertSame('fallback', env('KATAKATA_TEST_ENV', 'fallback'));
    }

    public function testEmptyVariableFromProcessEnvironmentCountsAsUnset(): void
    {
        putenv('KATAKATA_TEST_ENV=');

        self::assertSame('fallback', env('KATAKATA_TEST_ENV', 'fallback'));
    }

    public function testNewsletterSecretFallsBackToAppKeyWhenPresentButEmpty(): void
    {
        putenv('APP_KEY=stock-app-key');
        putenv('NEWSLETTER_SECRET=');

        $config = require dirname(__DIR__, 2) . '/config/newsletter.php';

        self::assertSame('stock-app-key', $config['secret']);
    }

    public function testAnalyticsSecretFallsBackToAppKeyWhenPresentButEmpty(): void
    {
        putenv('APP_KEY=stock-app-key');
        putenv('ANALYTICS_SECRET=');

        $config = require dirname(__DIR__, 2) . '/config/analytics.php';

        self::assertSame('stock-app-key', $config['secret']);
    }

    public function testDedicatedSecretsStillWinOverAppKey(): void
    {
        putenv('APP_KEY=stock-app-key');
        putenv('NEWSLETTER_SECRET=dedicated-newsletter');
        putenv('ANALYTICS_SECRET=dedicated-analytics');

        $newsletter = require dirname(__DIR__, 2) . '/config/newsletter.php';
        $analytics = require dirname(__DIR__, 2) . '/config/analytics.php';

        self::assertSame('dedicated-newsletter', $newsletter['secret']);
        self::assertSame('dedicated-analytics', $analytics['secret']);
    }

    public function testStockEnvFileStillResolvesAppKeyFallback(): void
    {
        $path = sys_get_temp_dir() . '/katakata-env-' . bin2hex(random_bytes(6));
        file_put_contents($path, "APP_KEY=stock-app-key\nNEWSLETTER_SECRET=\nANALYTICS_SECRET=\n");

        try {
            DotEnv::load($path);

            $newsletter = require dirname(__DIR__, 2) . '/config/newsletter.php';
            $analytics = require dirname(__DIR__, 2) . '/config/analytics.php';

            self::assertSame('stock-app-key', $newsletter['secret']);
            self::assertSame('stock-app-key', $analytics['secret']);
        } finally {
            unlink($path);
        }
    }
}

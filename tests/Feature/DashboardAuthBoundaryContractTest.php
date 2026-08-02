<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class DashboardAuthBoundaryContractTest extends TestCase
{
    public function testDashboardKeepsFourCardsAndReducesActivityNoise(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/dashboard.php');
        self::assertIsString($view);
        self::assertStringContainsString('foreach ($cards as $card)', $view);
        self::assertStringContainsString('array_slice($recentVisits, 0, 5)', $view);
        self::assertStringContainsString('>View analytics<', $view);
        self::assertStringContainsString('if ($buzz !== null)', $view);
        self::assertStringNotContainsString('$user[\'email\']', $view);
        self::assertStringNotContainsString('analytics:check', $view);
        self::assertStringContainsString('/assets/css/boundary.css', $view);
    }

    public function testLoginKeepsPasswordPrimaryAndUsesCompactPasskeyAlternative(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/auth.php');
        $script = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/passkeys.js');
        $css = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/boundary.css');
        self::assertIsString($view);
        self::assertIsString($script);
        self::assertIsString($css);
        self::assertStringContainsString('data-password-login', $view);
        self::assertStringContainsString('Private access for the publication team.', $view);
        self::assertStringContainsString('Use a passkey instead', $view);
        self::assertSame(1, substr_count($view, 'name="email"'));
        self::assertStringContainsString('data-passkey-submit', $view);
        self::assertStringContainsString('[data-password-login] input[name="email"]', $script);
        self::assertStringContainsString('Enter your email above first.', $script);
        self::assertStringContainsString('.auth-alternative', $css);
        self::assertStringContainsString('/assets/css/boundary.css', $view);
    }
}

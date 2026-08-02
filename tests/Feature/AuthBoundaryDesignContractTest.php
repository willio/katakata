<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class AuthBoundaryDesignContractTest extends TestCase
{
    public function testLoginKeepsPasswordAsThePrimaryForm(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/auth.php');

        self::assertIsString($view);
        self::assertStringContainsString('data-password-login', $view);
        self::assertStringContainsString('name="email"', $view);
        self::assertStringContainsString('name="password"', $view);
        self::assertStringContainsString('type="submit"', $view);
        self::assertStringContainsString('Private access for the publication team.', $view);
    }

    public function testPasskeyIsACompactAlternativeThatReusesThePrimaryEmail(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/auth.php');
        $script = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/passkeys.js');
        $css = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/boundary.css');

        self::assertIsString($view);
        self::assertIsString($script);
        self::assertIsString($css);
        self::assertStringContainsString('data-passkey-login', $view);
        self::assertStringContainsString('Use a passkey instead', $view);
        self::assertSame(1, substr_count($view, 'name="email"'));
        self::assertStringContainsString("document.querySelector('[data-password-login] input[name=\"email\"]')", $script);
        self::assertStringContainsString('Enter your email above first.', $script);
        self::assertStringContainsString('.auth-alternative', $css);
        self::assertStringContainsString('background: transparent', $css);
    }

    public function testPasskeyAlternativeIsHiddenWhenUnsupported(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/passkeys.js');

        self::assertIsString($script);
        self::assertStringContainsString('if (!passkeysSupported)', $script);
        self::assertStringContainsString('control.hidden = true;', $script);
    }
}

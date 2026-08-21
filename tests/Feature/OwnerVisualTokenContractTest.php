<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class OwnerVisualTokenContractTest extends TestCase
{
    public function testOwnerSurfacesUseOneRestrainedControlRadius(): void
    {
        $boundary = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/boundary.css');
        $site = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/site.css');
        $dashboard = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/dashboard-redesign.css');
        $posts = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/posts.css');
        $mail = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/mail.css');

        self::assertIsString($boundary);
        self::assertIsString($site);
        self::assertIsString($dashboard);
        self::assertIsString($posts);
        self::assertIsString($mail);
        self::assertStringContainsString('--radius-control: 16px', $site);
        self::assertStringContainsString('border-radius: var(--radius-control)', $boundary);
        self::assertStringContainsString('.mail-refresh-button', $mail);
        self::assertStringContainsString('.mail-page button', $boundary);
        self::assertStringContainsString('.editor-page button:not(.editor-settings-toggle):not(.editor-panel-close)', $boundary);
        self::assertStringContainsString('.mail-readiness', $boundary);
        self::assertStringNotContainsString('--workspace-radius', $dashboard);
        self::assertStringNotContainsString('border-radius: var(--workspace-radius)', $dashboard);
        self::assertStringNotContainsString('border-radius: 6px', $posts);
        self::assertStringNotContainsString('border-radius: 6px', $mail);
    }

    public function testPillsRemainLimitedToCompactFiltersAndStateBadges(): void
    {
        $posts = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/posts.css');
        $mail = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/mail.css');

        self::assertIsString($posts);
        self::assertIsString($mail);
        self::assertStringContainsString('.posts-filters a', $posts);
        self::assertStringContainsString('border-radius: 999px', $posts);
        self::assertStringContainsString('.mail-status-pill', $mail);
        self::assertStringContainsString('border-radius: 999px', $mail);
    }

    public function testPillVariantOverridesStayScopedToButtonsPill(): void
    {
        $boundary = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/boundary.css');

        self::assertIsString($boundary);
        self::assertStringContainsString('body.buttons-pill .button,', $boundary);
        self::assertStringContainsString('body.buttons-pill .dashboard-shell button:not(.field-clear):not(.editor-panel-close),', $boundary);
        self::assertStringContainsString('body.buttons-pill .settings-section button,', $boundary);
        self::assertStringContainsString('body.buttons-pill .auth-shell .button,', $boundary);
        self::assertStringContainsString('body.buttons-pill .mail-page button,', $boundary);
        self::assertStringContainsString('body.buttons-pill .editor-page button:not(.editor-settings-toggle):not(.editor-panel-close) {', $boundary);
        self::assertStringContainsString('border-radius: 999px', $boundary);
        self::assertStringContainsString('body.buttons-pill .field-clear', $boundary);
        self::assertStringContainsString('width: 32px', $boundary);
        self::assertStringContainsString('height: 32px', $boundary);
        self::assertStringContainsString('border-radius: 50%', $boundary);
        // Default 16px control radius stays intact outside pill mode.
        self::assertStringNotContainsString('--radius-control:', $boundary);
        self::assertStringContainsString('border-radius: var(--radius-control)', $boundary);
        self::assertStringNotContainsString('border-radius: 999px', substr($boundary, 0, (int) strpos($boundary, 'body.buttons-pill')));
    }

    public function testKeyboardFocusIsVisibleAcrossOwnerRoutes(): void
    {
        $boundary = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/boundary.css');
        $posts = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/posts.css');

        self::assertIsString($boundary);
        self::assertIsString($posts);
        self::assertStringContainsString(':focus-visible', $boundary);
        self::assertStringContainsString('outline: 2px solid var(--accent)', $boundary);
        self::assertStringContainsString('.mail-page a:focus-visible', $boundary);
        self::assertStringContainsString('.focused-mail-editor a:focus-visible', $boundary);
        self::assertStringNotContainsString('outline: 2px solid var(--accent)', $posts);
    }

    public function testExistingOwnerSafetyContractsRemainVisible(): void
    {
        $mailboxes = file_get_contents(dirname(__DIR__, 2) . '/resources/views/dashboard-settings-mailboxes.php');
        $posts = file_get_contents(dirname(__DIR__, 2) . '/resources/views/posts.php');
        $auth = file_get_contents(dirname(__DIR__, 2) . '/resources/views/auth.php');

        self::assertIsString($mailboxes);
        self::assertIsString($posts);
        self::assertIsString($auth);
        self::assertStringContainsString('name="confirm" required', $mailboxes);
        self::assertStringContainsString('Type <strong>', $mailboxes);
        self::assertStringContainsString('posts-index-title', $posts);
        self::assertStringContainsString('Use a passkey instead', $auth);
    }
}

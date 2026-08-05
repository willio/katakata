<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class OwnerVisualTokenContractTest extends TestCase
{
    public function testOwnerSurfacesUseOneRestrainedControlRadius(): void
    {
        $boundary = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/boundary.css');
        $posts = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/posts.css');
        $mail = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/mail.css');

        self::assertIsString($boundary);
        self::assertIsString($posts);
        self::assertIsString($mail);
        self::assertStringContainsString('--radius-control: 6px', $boundary);
        self::assertStringContainsString('border-radius: var(--radius-control)', $boundary);
        self::assertStringContainsString('.owner-header .button', $posts);
        self::assertStringContainsString('border-radius: 6px', $posts);
        self::assertStringContainsString('.mail-refresh-button', $mail);
        self::assertStringContainsString('border-radius: 6px', $mail);
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

    public function testKeyboardFocusIsVisibleAcrossOwnerRoutes(): void
    {
        $boundary = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/boundary.css');
        $posts = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/posts.css');

        self::assertIsString($boundary);
        self::assertIsString($posts);
        self::assertStringContainsString(':focus-visible', $boundary);
        self::assertStringContainsString('outline: 2px solid var(--accent)', $boundary);
        self::assertStringContainsString(':focus-visible', $posts);
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

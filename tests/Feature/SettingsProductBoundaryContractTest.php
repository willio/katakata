<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class SettingsProductBoundaryContractTest extends TestCase
{
    public function testGlobalSettingsExposeOnlyPurposefulProductSections(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/dashboard-settings.php');
        self::assertIsString($view);
        foreach (['Publication', 'Newsletter', 'Reader inbox', 'Discussion', 'Analytics', 'Appearance'] as $label) self::assertStringContainsString($label, $view);
        self::assertStringNotContainsString('Account &amp; Security</a>', $view);
        self::assertStringNotContainsString('System</a>', $view);
        self::assertStringNotContainsString('<dt>Host</dt>', $view);
        self::assertStringNotContainsString('Deployment variables required', $view);
        self::assertStringNotContainsString('private/jobs/sync-mail.php', $view);
        self::assertStringNotContainsString('storage/mail/cache', $view);
        foreach (['Available', 'Waiting for setup', 'Needs attention', 'Paused'] as $state) self::assertStringContainsString($state, $view);
    }

    public function testMailboxManagementDoesNotRenderConnectionTopology(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/dashboard-settings-mailboxes.php');
        self::assertIsString($view);
        self::assertStringContainsString('<h1>Reader inboxes</h1>', $view);
        self::assertStringContainsString('Save inbox name', $view);
        self::assertStringContainsString('Pause inbox', $view);
        self::assertStringContainsString('Resume inbox', $view);
        self::assertStringNotContainsString('Account ID</label>', $view);
        self::assertStringNotContainsString('IMAP host</label>', $view);
        self::assertStringNotContainsString('Port</label>', $view);
        self::assertStringNotContainsString('Mailbox</label>', $view);
        self::assertStringNotContainsString('Username variable name', $view);
        self::assertStringNotContainsString('Password variable name', $view);
        self::assertStringNotContainsString('Missing deployment variables', $view);
        self::assertStringNotContainsString('private/jobs/sync-mail.php', $view);
    }
}

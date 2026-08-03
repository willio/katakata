<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class BoundaryAuditRegressionTest extends TestCase
{
    public function testMailboxRemovalRequiresTypedAccountId(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/dashboard-settings-mailboxes.php');
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/settings-mailboxes.php');

        self::assertIsString($view);
        self::assertIsString($routes);
        self::assertStringContainsString('Type <strong><?= e($account->id) ?></strong> to confirm removal.', $view);
        self::assertStringContainsString('name="confirm" required autocomplete="off"', $view);
        self::assertStringNotContainsString('type="hidden" name="confirm"', $view);
        self::assertStringContainsString("(\$request->body['confirm'] ?? '') !== \$id", $routes);
    }

    public function testEmptyCampaignReaderShowsOnlyConciseSelectionState(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail.php');

        self::assertIsString($view);
        self::assertStringContainsString("if (\$campaign === null)", $view);
        self::assertStringContainsString('Select a campaign.', $view);
        self::assertStringContainsString("<h2 id=\"mail-detail-title\"><?= e(\$campaign['post']['title']) ?></h2>", $view);
        self::assertStringNotContainsString('<h2 id="mail-detail-title">Campaign detail</h2>', $view);
        self::assertStringNotContainsString('<h3>Selected candidate</h3>', $view);
    }
}

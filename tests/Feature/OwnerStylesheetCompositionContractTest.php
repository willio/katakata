<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OwnerStylesheetCompositionContractTest extends TestCase
{
    /**
     * @param list<string> $expectedStylesheets
     */
    #[DataProvider('ownerViews')]
    public function testOwnerViewsComposeSharedLayersBeforeRouteStylesheets(
        string $view,
        array $expectedStylesheets,
    ): void {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/resources/views/' . $view);

        self::assertIsString($contents);
        preg_match_all(
            '/<link\s+rel="stylesheet"\s+href="([^"]+)">/',
            $contents,
            $matches,
        );

        self::assertSame($expectedStylesheets, $matches[1], $view);
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function ownerViews(): iterable
    {
        $site = '/assets/css/site.css';
        $boundary = '/assets/css/boundary.css';

        yield 'authentication' => ['auth.php', [$site, $boundary]];
        yield 'dashboard' => [
            'dashboard.php',
            [$site, $boundary, '/assets/css/dashboard-redesign.css'],
        ];
        yield 'analytics' => ['analytics.php', [$site, $boundary]];
        yield 'settings' => ['dashboard-settings.php', [$site, $boundary]];
        yield 'mailbox settings' => ['dashboard-settings-mailboxes.php', [$site, $boundary]];
        yield 'mailbox import' => ['dashboard-settings-mailbox-import.php', [$site, $boundary]];
        yield 'posts' => ['posts.php', [$site, $boundary, '/assets/css/posts.css']];
        yield 'editor' => ['editor.php', [$site, $boundary]];
        yield 'mail workspace' => ['mail.php', [$site, $boundary, '/assets/css/mail.css']];
        yield 'mail archive' => ['mail-archive.php', [$site, $boundary, '/assets/css/mail.css']];
        yield 'mail message' => ['mail-message.php', [$site, $boundary, '/assets/css/mail.css']];
        yield 'sent mail' => ['mail-sent.php', [$site, $boundary]];
        yield 'compose mail' => ['mail-compose.php', [$site, $boundary, '/assets/css/mail.css']];
        yield 'correspondence editor' => [
            'mail-draft-editor.php',
            [$site, $boundary, '/assets/css/mail.css', '/assets/css/focused-editor.css'],
        ];
        yield 'campaign editor' => [
            'mail-campaign-draft.php',
            [$site, $boundary, '/assets/css/mail.css', '/assets/css/focused-editor.css'],
        ];
        yield 'campaign history' => ['mail-campaigns.php', [$site, $boundary]];
        yield 'campaign detail' => ['mail-campaign.php', [$site, $boundary]];
        yield 'campaign confirmation' => ['mail-confirm.php', [$site, $boundary]];
    }
}

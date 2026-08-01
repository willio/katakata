<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class CampaignDraftRouteContractTest extends TestCase
{
    public function testPostHandoffIsNonDestructiveAndMailAuthorized(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/editor.php');

        self::assertIsString($routes);
        self::assertStringContainsString("'/editor/posts/{slug}/campaign-drafts'", $routes);
        self::assertStringContainsString('!$session->canManageMail()', $routes);
        self::assertStringContainsString("hash('sha256', \$before)", $routes);
        self::assertStringContainsString("hash('sha256', \$after)", $routes);
        self::assertStringContainsString("Response::html('Source post changed while creating campaign draft.', 500)", $routes);
        self::assertStringContainsString("CampaignDraftStore::class)->create", $routes);
        self::assertStringContainsString("'/mail/campaign-drafts/'", $routes);
    }

    public function testCampaignDraftRoutesExposeVersionedSaveReviewAndConfirmTransitions(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/campaign.php');

        self::assertIsString($routes);
        foreach ([
            '/mail/campaign-drafts/{id}',
            '/mail/campaign-drafts/{id}/autosave',
            '/mail/campaign-drafts/{id}/confirm',
            '/mail/campaign/{id}/drafts',
        ] as $path) {
            self::assertStringContainsString($path, $routes);
        }

        self::assertStringContainsString("'expected_version'", $routes);
        self::assertStringContainsString('CampaignDraftConflict', $routes);
        self::assertStringContainsString("], 409)", $routes);
        self::assertStringContainsString('CampaignDraftReviewer::class', $routes);
        self::assertStringContainsString('confirmDraftAndQueue', $routes);
    }

    public function testCampaignDraftStorageAndComposerKeepPrivateVersionedState(): void
    {
        $bootstrap = file_get_contents(dirname(__DIR__, 2) . '/bootstrap/mail.php');
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail-campaign-draft.php');
        $script = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/campaign-draft.js');

        self::assertIsString($bootstrap);
        self::assertIsString($view);
        self::assertIsString($script);
        self::assertStringContainsString("storagePath('mail/campaign-drafts')", $bootstrap);
        self::assertStringContainsString('name="expected_version"', $view);
        self::assertStringContainsString('Review campaign', $view);
        self::assertStringContainsString('Confirm and queue', $view);
        self::assertStringContainsString('aria-pressed="false"', $view);
        self::assertStringContainsString("classList.toggle('campaign-compose-fullscreen'", $script);
        self::assertStringNotContainsString('cloneNode(', $script);
    }
}

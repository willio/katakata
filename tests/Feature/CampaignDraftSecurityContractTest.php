<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class CampaignDraftSecurityContractTest extends TestCase
{
    public function testCampaignDraftRoutesRequireMailManagementPermission(): void
    {
        $editorRoutes = file_get_contents(dirname(__DIR__, 2) . '/routes/editor.php');
        $campaignRoutes = file_get_contents(dirname(__DIR__, 2) . '/routes/campaign.php');

        self::assertIsString($editorRoutes);
        self::assertIsString($campaignRoutes);

        self::assertStringContainsString('/editor/posts/{slug}/campaign-drafts', $editorRoutes);
        self::assertStringContainsString('!$session->canManageMail()', $editorRoutes);
        self::assertStringContainsString("Response::html('Forbidden.', 403)", $editorRoutes);

        foreach ([
            '/mail/campaign-drafts/{id}',
            '/mail/campaign-drafts/{id}/autosave',
            '/mail/campaign-drafts/{id}/confirm',
            '/mail/campaign/{id}/drafts',
        ] as $path) {
            self::assertStringContainsString($path, $campaignRoutes);
        }

        self::assertStringContainsString('!$session->canManageMail()', $campaignRoutes);
        self::assertStringContainsString("Response::html('Forbidden.', 403)", $campaignRoutes);
    }

    public function testDraftReviewDoesNotQueueOrSnapshotRecipients(): void
    {
        $draft = file_get_contents(dirname(__DIR__, 2) . '/app/Mail/CampaignDraft.php');
        $reviewer = file_get_contents(dirname(__DIR__, 2) . '/app/Mail/CampaignDraftReviewer.php');
        $dispatcher = file_get_contents(dirname(__DIR__, 2) . '/app/Mail/CampaignDispatcher.php');

        self::assertIsString($draft);
        self::assertIsString($reviewer);
        self::assertIsString($dispatcher);

        self::assertStringNotContainsString('recipients', $draft);
        self::assertStringContainsString('$this->subscribers->deliverable()', $reviewer);
        self::assertStringNotContainsString('MailQueue', $reviewer);
        self::assertStringContainsString('confirmDraftAndQueue', $dispatcher);
        self::assertStringContainsString("recipients: \$proof['recipients']", $dispatcher);
    }

    public function testCampaignComposeUsesOneFocusedEditorState(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/campaign-draft.js');
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/mail-campaign-draft.php');

        self::assertIsString($script);
        self::assertIsString($view);

        self::assertStringContainsString('editor-page mail-draft-editor-page campaign-draft-editor-page', $view);
        self::assertStringContainsString('data-campaign-draft', $view);
        self::assertStringContainsString('/assets/js/editor-autosave.js', $view);
        self::assertStringNotContainsString('campaign-fullscreen-toggle', $view);
        self::assertStringNotContainsString("classList.toggle('campaign-compose-fullscreen'", $script);
        self::assertStringNotContainsString('cloneNode', $script);
        self::assertStringNotContainsString('window.open', $script);
    }
}

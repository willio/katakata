<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use DateTimeImmutable;
use Katakata\Analytics\AnalyticsStore;
use Katakata\Auth\AccountStore;
use Katakata\Auth\Session;
use Katakata\Content\Draft;
use Katakata\Content\Repository;
use Katakata\Dashboard\DashboardSettings;
use Katakata\Distribution\EmailTransport;
use Katakata\Distribution\ThreadsApi;
use Katakata\Editorial\AtomicFile;
use Katakata\Editorial\DraftEditor;
use Katakata\Editorial\RevisionStore;
use Katakata\Http\Request;
use Katakata\View;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EditorSettingsScopeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-editor-settings-' . bin2hex(random_bytes(6));
        foreach (['posts', 'drafts', 'authors', 'assets', 'revisions', 'auth'] as $directory) {
            mkdir($this->root . '/' . $directory, 0775, true);
        }
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $this->remove($this->root);
    }

    public function testEditorSettingsShowOnlyPostControlsAndLinkToGlobalSettings(): void
    {
        $draft = new Draft(
            slug: 'scoped-settings',
            title: 'Scoped settings',
            updatedAt: new DateTimeImmutable('2026-08-01T00:00:00+00:00'),
            body: 'Body.',
            meta: [
                'publish_as_newsletter' => 'true',
                'discussion_enabled' => 'true',
            ],
            path: '/tmp/scoped-settings.md',
        );

        $html = (new View(dirname(__DIR__, 2) . '/resources/views'))->render('editor', [
            'user' => ['email' => 'owner@example.test'],
            'drafts' => [],
            'draft' => $draft,
            'csrf' => 'test-token',
            'notice' => null,
            'draftVersion' => 'version-1',
        ]);

        self::assertStringContainsString('href="/dashboard/settings"', $html);
        self::assertMatchesRegularExpression('/name="publish_as_newsletter"[^>]*checked/', $html);
        self::assertMatchesRegularExpression('/name="discussion_enabled"[^>]*checked/', $html);
        self::assertStringNotContainsString('name="provider"', $html);
        self::assertStringNotContainsString('name="publication_name"', $html);
        self::assertStringNotContainsString('data-passkey-register', $html);
        self::assertStringNotContainsString('form="invite-form"', $html);
    }

    public function testEditorDoesNotResolveGlobalServicesAndPreservesPostFlags(): void
    {
        $files = new AtomicFile();
        $editor = new DraftEditor(
            $this->root . '/drafts',
            $files,
            new RevisionStore($this->root . '/revisions', $files),
        );
        $editor->save('scoped-settings', 'Scoped settings', 'Original body.', [
            'author' => 'writer',
            'excerpt' => 'Keep this excerpt.',
            'publish_as_newsletter' => 'false',
            'discussion_enabled' => 'false',
        ]);
        $repository = new Repository(
            $this->root . '/posts',
            $this->root . '/drafts',
            $this->root . '/authors',
            $this->root . '/assets',
        );
        $accounts = new AccountStore($this->root . '/auth/accounts.json', $files);
        $account = $accounts->createOwner('owner@example.test', 'a-secure-test-password');
        $session = new Session($accounts);
        $session->start();
        $_SESSION = [
            'account_id' => $account['id'],
            'csrf' => 'test-token',
        ];

        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $app->instance(Session::class, $session);
        $app->instance(Repository::class, $repository);
        $app->instance(DraftEditor::class, $editor);
        foreach ([ThreadsApi::class, EmailTransport::class, AnalyticsStore::class, DashboardSettings::class] as $service) {
            $app->bind($service, static fn (): never => throw new RuntimeException("Editor resolved {$service}."));
        }
        $router = require dirname(__DIR__, 2) . '/bootstrap/routes.php';

        self::assertSame(200, $router->dispatch(new Request(
            'GET',
            '/editor/drafts/scoped-settings',
        ))->status);

        $response = $router->dispatch(new Request(
            'POST',
            '/editor/drafts/scoped-settings/autosave',
            body: [
                'csrf' => 'test-token',
                'title' => 'Scoped settings',
                'body' => 'Updated body.',
                'publish_as_newsletter' => '1',
                'discussion_enabled' => '1',
            ],
        ));

        self::assertSame(200, $response->status, $response->body);
        $repository->refresh();
        $saved = $repository->findDraft('scoped-settings');
        self::assertNotNull($saved);
        self::assertSame('writer', $saved->meta['author']);
        self::assertSame('Keep this excerpt.', $saved->meta['excerpt']);
        self::assertTrue($saved->meta['publish_as_newsletter']);
        self::assertTrue($saved->meta['discussion_enabled']);
    }

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $target = $path . '/' . $entry;
            is_dir($target) ? $this->remove($target) : unlink($target);
        }
        rmdir($path);
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use DateTimeImmutable;
use Katakata\Auth\AccountStore;
use Katakata\Auth\Session;
use Katakata\Dashboard\DashboardSettings;
use Katakata\Editorial\AtomicFile;
use Katakata\Email\Draft;
use Katakata\Email\DraftStore;
use Katakata\Email\FileDraftStore;
use Katakata\Email\OutboundMailProvider;
use Katakata\Http\Request;
use Katakata\Http\Router;
use Katakata\Settings\SettingsStore;
use PHPUnit\Framework\TestCase;

final class MailWorkspaceRegressionTest extends TestCase
{
    private string $root;
    private AccountStore $accounts;
    /** @var array<string, mixed> */
    private array $owner;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-mail-regression-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0700, true);
        $this->accounts = new AccountStore($this->root . '/accounts.json', new AtomicFile());
        $this->owner = $this->accounts->createOwner('owner@example.test', 'owner-password-123');
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        foreach (glob($this->root . '/drafts/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->root . '/drafts');
        @unlink($this->root . '/accounts.json');
        @rmdir($this->root);
    }

    public function testMailboxImportPostIsRegisteredBeforeTheParameterizedMailboxRoute(): void
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $router = require dirname(__DIR__, 2) . '/bootstrap/routes.php';
        $routes = array_values($router->all());

        $import = array_search(
            ['method' => 'POST', 'path' => '/dashboard/settings/mailboxes/import'],
            $routes,
            true,
        );
        $parameterized = array_search(
            ['method' => 'POST', 'path' => '/dashboard/settings/mailboxes/{id}'],
            $routes,
            true,
        );

        self::assertIsInt($import);
        self::assertIsInt($parameterized);
        self::assertLessThan($parameterized, $import);
    }

    public function testMailboxImportPostReachesTheImportHandler(): void
    {
        $router = $this->routerFor();
        $session = $this->accountsSession();

        $response = $router->dispatch(new Request('POST', '/dashboard/settings/mailboxes/import', body: [
            'csrf' => $session->csrf(),
        ]));

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Choose a valid .mobileconfig or XML plist file.', $response->body);
    }

    public function testMalformedCorrespondenceDraftIdsReturnNotFound(): void
    {
        $router = $this->routerFor();
        $this->accountsSession();

        foreach (['/mail/drafts/nonexistent', '/mail/drafts/nonexistent/edit'] as $path) {
            $response = $router->dispatch(new Request('GET', $path));
            self::assertSame(404, $response->status);
            self::assertStringNotContainsString('RuntimeException', $response->body);
        }
    }

    public function testMalformedCampaignDraftIdsReturnNotFound(): void
    {
        $router = $this->routerFor();
        $this->accountsSession();

        $response = $router->dispatch(new Request('GET', '/mail/campaign-drafts/nonexistent'));

        self::assertSame(404, $response->status);
        self::assertStringNotContainsString('RuntimeException', $response->body);
    }

    public function testComposeDisablesSendWhenNoOutboundProviderIsBound(): void
    {
        $router = $this->routerFor();
        $this->accountsSession();

        $response = $router->dispatch(new Request('GET', '/mail/drafts/' . $this->draftId() . '/edit'));

        self::assertSame(200, $response->status);
        self::assertStringContainsString('name="intent" value="send" disabled', $response->body);
        self::assertStringContainsString('Correspondence sending is not configured on this server.', $response->body);
    }

    public function testComposeEnablesSendWhenAnOutboundProviderIsBound(): void
    {
        $router = $this->routerFor(new class implements OutboundMailProvider {
            public function send(Draft $draft): void
            {
            }
        });
        $this->accountsSession();

        $response = $router->dispatch(new Request('GET', '/mail/drafts/' . $this->draftId() . '/edit'));

        self::assertSame(200, $response->status);
        self::assertStringNotContainsString('value="send" disabled', $response->body);
        self::assertStringNotContainsString('Correspondence sending is not configured on this server.', $response->body);
    }

    private function routerFor(?OutboundMailProvider $outbound = null): Router
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $app->instance(Session::class, $this->accountsSession());
        $app->instance(DashboardSettings::class, new DashboardSettings(
            new SettingsStore($this->root . '/application.json', new AtomicFile()),
            ['appearance' => ['theme' => 'default', 'button_style' => 'regular']],
        ));
        $app->instance(DraftStore::class, new FileDraftStore($this->root . '/drafts', new AtomicFile()));
        if ($outbound !== null) {
            $app->instance(OutboundMailProvider::class, $outbound);
        }

        return require dirname(__DIR__, 2) . '/bootstrap/routes.php';
    }

    private function draftId(): string
    {
        $id = str_repeat('e', 32);
        $now = new DateTimeImmutable('2026-08-02T10:00:00+00:00');
        (new FileDraftStore($this->root . '/drafts', new AtomicFile()))
            ->create(new Draft($id, 'reader@example.test', 'Subject', 'Body', null, 1, $now, $now));
        return $id;
    }

    private function accountsSession(): Session
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        session_id('mail-regression-' . bin2hex(random_bytes(8)));

        $session = new Session($this->accounts);
        $session->login($this->owner);

        return $session;
    }
}

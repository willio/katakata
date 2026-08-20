<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use Katakata\Auth\AccountStore;
use Katakata\Auth\Session;
use Katakata\Dashboard\DashboardSettings;
use Katakata\Editorial\AtomicFile;
use Katakata\Http\Request;
use Katakata\Http\Router;
use Katakata\Settings\SecretsStore;
use Katakata\Settings\SettingsStore;
use Katakata\View;
use PHPUnit\Framework\TestCase;

final class DashboardSettingsRoutesTest extends TestCase
{
    private string $root;
    private AccountStore $accounts;
    /** @var array<string, mixed> */
    private array $owner;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-settings-routes-' . bin2hex(random_bytes(6));
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
        @unlink($this->root . '/accounts.json');
        @unlink($this->root . '/application.json');
        @unlink($this->root . '/secrets.json');
        @rmdir($this->root);
    }

    public function testSettingsRoutesAreComposedExactlyOnce(): void
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $router = require dirname(__DIR__, 2) . '/bootstrap/routes.php';
        $routes = $router->all();

        foreach (['GET', 'POST'] as $method) {
            $matches = array_filter(
                $routes,
                static fn (array $route): bool => $route === [
                    'method' => $method,
                    'path' => '/dashboard/settings',
                ],
            );
            self::assertCount(1, $matches);
        }
    }

    public function testGuestSettingsRequestsRedirectToLogin(): void
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $router = require dirname(__DIR__, 2) . '/bootstrap/routes.php';

        foreach ([
            new Request('GET', '/dashboard/settings'),
            new Request('POST', '/dashboard/settings'),
        ] as $request) {
            $response = $router->dispatch($request);
            self::assertSame(302, $response->status);
            self::assertSame('/login', $response->headers['Location']);
        }
    }

    public function testSettingsRoutesRequireOwnerOrAdminPermission(): void
    {
        $session = file_get_contents(dirname(__DIR__, 2) . '/app/Auth/Session.php');
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/settings.php');

        self::assertIsString($session);
        self::assertIsString($routes);
        self::assertStringContainsString('public function canManageSettings(): bool', $session);
        self::assertStringContainsString("return \$this->hasRole('owner', 'admin');", $session);
        self::assertStringContainsString('!$session->canManageSettings()', $routes);
        self::assertStringContainsString("Response::html('Forbidden.', 403)", $routes);
    }

    public function testSettingsRouteIsBeforeTheGreedyArticleRoute(): void
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $router = require dirname(__DIR__, 2) . '/bootstrap/routes.php';
        $routes = array_values($router->all());

        $settings = array_search(
            ['method' => 'GET', 'path' => '/dashboard/settings'],
            $routes,
            true,
        );
        $article = array_search(
            ['method' => 'GET', 'path' => '/{year}/{month}/{slug}'],
            $routes,
            true,
        );

        self::assertIsInt($settings);
        self::assertIsInt($article);
        self::assertLessThan($article, $settings);
    }

    public function testAppearanceSectionSavesButtonStyle(): void
    {
        $router = $this->routerFor();
        $session = $this->accountsSession();

        $response = $router->dispatch(new Request('POST', '/dashboard/settings', body: [
            'csrf' => $session->csrf(),
            'section' => 'appearance',
            'theme' => 'warm',
            'button_style' => 'pill',
        ]));

        self::assertSame(303, $response->status);
        self::assertSame('/dashboard/settings?saved=1#appearance', $response->headers['Location']);

        $stored = json_decode((string) file_get_contents($this->root . '/application.json'), true);
        self::assertSame('pill', $stored['appearance']['button_style'] ?? null);
        self::assertSame('warm', $stored['appearance']['theme'] ?? null);
    }

    public function testAppearanceSectionRejectsInvalidButtonStyle(): void
    {
        $router = $this->routerFor();
        $session = $this->accountsSession();

        $response = $router->dispatch(new Request('POST', '/dashboard/settings', body: [
            'csrf' => $session->csrf(),
            'section' => 'appearance',
            'theme' => 'default',
            'button_style' => 'rounded',
        ]));

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Appearance button style is invalid.', $response->body);
        self::assertFileDoesNotExist($this->root . '/application.json');
    }

    public function testSettingsPageRendersButtonStyleSelectWithCurrentValueSelected(): void
    {
        $html = (new View(dirname(__DIR__, 2) . '/resources/views'))->render('dashboard-settings', [
            'user' => ['email' => 'owner@example.test'],
            'siteName' => 'Katakata',
            'settings' => [
                'publication' => ['name' => 'Katakata', 'description' => '', 'default_author' => ''],
                'newsletter' => ['sender_label' => '', 'publish_by_default' => false],
                'discussion' => ['provider' => 'none', 'enabled_by_default' => false],
                'analytics' => ['dashboard_period' => '30d'],
                'appearance' => ['theme' => 'default', 'button_style' => 'pill'],
            ],
            'readiness' => [
                'newsletter' => ['status' => 'Ready', 'detail' => 'Ready.'],
                'mailbox' => ['status' => 'disabled', 'accounts' => []],
                'discussion' => ['status' => 'Disabled', 'detail' => 'Disabled.'],
                'analytics' => ['status' => 'Ready', 'detail' => 'Ready.'],
                'appearance' => ['status' => 'Partially available', 'detail' => 'Button shape is applied.'],
            ],
            'saved' => false,
            'error' => null,
            'csrf' => 'test-token',
        ]);

        self::assertStringContainsString('<a href="#appearance">Appearance</a>', $html);
        self::assertStringContainsString('name="button_style"', $html);
        self::assertStringContainsString('<option value="pill" selected>', $html);
        self::assertStringContainsString('name="theme" value="default"', $html);
        self::assertStringContainsString('dashboard-settings-page buttons-pill', $html);
    }

    public function testDiscussionSectionPersistsThreadsCredentialReferences(): void
    {
        $router = $this->routerFor();
        $session = $this->accountsSession();

        $response = $router->dispatch(new Request('POST', '/dashboard/settings', body: [
            'csrf' => $session->csrf(),
            'section' => 'discussion',
            'provider' => 'native',
            'threads_user_id' => '123456789',
            'threads_token_secret' => 'KATAKATA_THREADS_TOKEN',
        ]));

        self::assertSame(303, $response->status);
        self::assertSame('/dashboard/settings?saved=1#discussion', $response->headers['Location']);

        $stored = json_decode((string) file_get_contents($this->root . '/application.json'), true);
        self::assertSame('123456789', $stored['discussion']['threads_user_id'] ?? null);
        self::assertSame('KATAKATA_THREADS_TOKEN', $stored['discussion']['threads_token_secret'] ?? null);
    }

    public function testDiscussionSectionRejectsInvalidThreadsTokenSecretName(): void
    {
        $router = $this->routerFor();
        $session = $this->accountsSession();

        $response = $router->dispatch(new Request('POST', '/dashboard/settings', body: [
            'csrf' => $session->csrf(),
            'section' => 'discussion',
            'provider' => 'native',
            'threads_user_id' => '123456789',
            'threads_token_secret' => 'not-a-valid-secret',
        ]));

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Threads token secret name is invalid.', $response->body);
        self::assertFileDoesNotExist($this->root . '/application.json');
    }

    public function testSettingsPageRendersThreadsFieldsWithoutRenderingTokenValue(): void
    {
        putenv('KATAKATA_TEST_THREADS_TOKEN=super-secret-token-value');
        try {
            $router = $this->routerFor();
            $session = $this->accountsSession();

            $saved = $router->dispatch(new Request('POST', '/dashboard/settings', body: [
                'csrf' => $session->csrf(),
                'section' => 'discussion',
                'provider' => 'threads',
                'threads_user_id' => '123456789',
                'threads_token_secret' => 'KATAKATA_TEST_THREADS_TOKEN',
            ]));
            self::assertSame(303, $saved->status);

            $response = $router->dispatch(new Request('GET', '/dashboard/settings'));

            self::assertSame(200, $response->status);
            self::assertStringContainsString('>Threads user ID</label>', $response->body);
            self::assertStringContainsString('name="threads_user_id" value="123456789"', $response->body);
            self::assertStringContainsString('>Threads token environment variable</label>', $response->body);
            self::assertStringContainsString('name="threads_token_secret" value="KATAKATA_TEST_THREADS_TOKEN"', $response->body);
            self::assertStringContainsString('>Ready</strong>', $response->body);
            self::assertStringNotContainsString('super-secret-token-value', $response->body);
        } finally {
            putenv('KATAKATA_TEST_THREADS_TOKEN');
        }
    }

    public function testDiscussionSectionStoresThreadsTokenValueEncrypted(): void
    {
        $router = $this->routerWithSecrets('test-app-key');
        $session = $this->accountsSession();

        $response = $router->dispatch(new Request('POST', '/dashboard/settings', body: $this->discussionBody($session, [
            'threads_token_value' => 'plain-threads-token-value',
            'confirm_password' => 'owner-password-123',
        ])));

        self::assertSame(303, $response->status);

        $raw = (string) file_get_contents($this->root . '/secrets.json');
        self::assertStringNotContainsString('plain-threads-token-value', $raw);

        $secrets = new SecretsStore($this->root . '/secrets.json', new AtomicFile(), 'test-app-key');
        self::assertSame('plain-threads-token-value', $secrets->get('threads.access_token'));

        $settings = (string) file_get_contents($this->root . '/application.json');
        self::assertStringNotContainsString('plain-threads-token-value', $settings);

        $page = $router->dispatch(new Request('GET', '/dashboard/settings'));
        self::assertSame(200, $page->status);
        self::assertStringContainsString('type="password" name="threads_token_value"', $page->body);
        self::assertStringContainsString('•••••••• — stored; leave empty to keep', $page->body);
        self::assertStringContainsString('name="threads_token_remove"', $page->body);
        self::assertStringContainsString('name="confirm_password"', $page->body);
        self::assertStringNotContainsString('plain-threads-token-value', $page->body);
    }

    public function testDiscussionTokenFieldRendersEmptyWithDeploymentFallbackHintWhenNothingStored(): void
    {
        $router = $this->routerWithSecrets('test-app-key');
        $this->accountsSession();

        $page = $router->dispatch(new Request('GET', '/dashboard/settings'));

        self::assertSame(200, $page->status);
        self::assertStringContainsString('type="password" name="threads_token_value"', $page->body);
        self::assertStringContainsString('name="confirm_password"', $page->body);
        self::assertStringContainsString('the deployment variable named above remains the fallback', $page->body);
        self::assertStringNotContainsString('stored; leave empty to keep', $page->body);
        self::assertStringNotContainsString('name="threads_token_remove"', $page->body);
    }

    public function testDiscussionEmptyTokenValuePreservesStoredSecret(): void
    {
        $router = $this->routerWithSecrets('test-app-key');
        $session = $this->accountsSession();

        $stored = $router->dispatch(new Request('POST', '/dashboard/settings', body: $this->discussionBody($session, [
            'threads_token_value' => 'plain-threads-token-value',
            'confirm_password' => 'owner-password-123',
        ])));
        self::assertSame(303, $stored->status);

        $response = $router->dispatch(new Request('POST', '/dashboard/settings', body: $this->discussionBody($session, [
            'provider' => 'none',
            'threads_token_value' => '',
        ])));

        self::assertSame(303, $response->status);
        $secrets = new SecretsStore($this->root . '/secrets.json', new AtomicFile(), 'test-app-key');
        self::assertSame('plain-threads-token-value', $secrets->get('threads.access_token'));
    }

    public function testDiscussionTokenChangeWithWrongPasswordPersistsNothing(): void
    {
        $router = $this->routerWithSecrets('test-app-key');
        $session = $this->accountsSession();

        $response = $router->dispatch(new Request('POST', '/dashboard/settings', body: $this->discussionBody($session, [
            'threads_token_value' => 'plain-threads-token-value',
            'confirm_password' => 'wrong-password-456',
        ])));

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Confirm your current password', $response->body);
        self::assertFileDoesNotExist($this->root . '/secrets.json');
        self::assertFileDoesNotExist($this->root . '/application.json');
    }

    public function testDiscussionTokenRemovalRequiresPasswordConfirmation(): void
    {
        $router = $this->routerWithSecrets('test-app-key');
        $session = $this->accountsSession();

        $stored = $router->dispatch(new Request('POST', '/dashboard/settings', body: $this->discussionBody($session, [
            'threads_token_value' => 'plain-threads-token-value',
            'confirm_password' => 'owner-password-123',
        ])));
        self::assertSame(303, $stored->status);

        $secrets = new SecretsStore($this->root . '/secrets.json', new AtomicFile(), 'test-app-key');

        $wrong = $router->dispatch(new Request('POST', '/dashboard/settings', body: $this->discussionBody($session, [
            'threads_token_remove' => '1',
            'confirm_password' => 'wrong-password-456',
        ])));
        self::assertSame(422, $wrong->status);
        self::assertStringContainsString('Confirm your current password', $wrong->body);
        self::assertTrue($secrets->has('threads.access_token'));

        $removed = $router->dispatch(new Request('POST', '/dashboard/settings', body: $this->discussionBody($session, [
            'threads_token_remove' => '1',
            'confirm_password' => 'owner-password-123',
        ])));
        self::assertSame(303, $removed->status);
        self::assertFalse($secrets->has('threads.access_token'));
    }

    public function testDiscussionTokenValueSurfacesAppKeyErrorWhenStoreUnavailable(): void
    {
        $router = $this->routerWithSecrets(null);
        $session = $this->accountsSession();

        $response = $router->dispatch(new Request('POST', '/dashboard/settings', body: $this->discussionBody($session, [
            'threads_token_value' => 'plain-threads-token-value',
            'confirm_password' => 'owner-password-123',
        ])));

        self::assertSame(422, $response->status);
        self::assertStringContainsString('APP_KEY', $response->body);
        self::assertFileDoesNotExist($this->root . '/secrets.json');
    }

    public function testThreadsReadinessReportsTokenSourceWithoutRenderingValues(): void
    {
        putenv('KATAKATA_TEST_THREADS_TOKEN=env-threads-token-value');
        try {
            $router = $this->routerWithSecrets('test-app-key');
            $session = $this->accountsSession();

            $saved = $router->dispatch(new Request('POST', '/dashboard/settings', body: $this->discussionBody($session, [
                'provider' => 'threads',
            ])));
            self::assertSame(303, $saved->status);

            $page = $router->dispatch(new Request('GET', '/dashboard/settings'));
            self::assertSame(200, $page->status);
            self::assertStringContainsString('Token source: From deployment configuration.', $page->body);

            $stored = $router->dispatch(new Request('POST', '/dashboard/settings', body: $this->discussionBody($session, [
                'provider' => 'threads',
                'threads_token_value' => 'managed-threads-token-value',
                'confirm_password' => 'owner-password-123',
            ])));
            self::assertSame(303, $stored->status);

            $page = $router->dispatch(new Request('GET', '/dashboard/settings'));
            self::assertSame(200, $page->status);
            self::assertStringContainsString('Token source: Managed in settings.', $page->body);
            self::assertStringNotContainsString('managed-threads-token-value', $page->body);
            self::assertStringNotContainsString('env-threads-token-value', $page->body);
        } finally {
            putenv('KATAKATA_TEST_THREADS_TOKEN');
        }
    }

    private function routerFor(): Router
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $app->instance(Session::class, $this->accountsSession());
        $app->instance(DashboardSettings::class, new DashboardSettings(
            new SettingsStore($this->root . '/application.json', new AtomicFile()),
            ['appearance' => ['theme' => 'default', 'button_style' => 'regular']],
        ));

        return require dirname(__DIR__, 2) . '/bootstrap/routes.php';
    }

    private function routerWithSecrets(?string $appKey): Router
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $app->instance(Session::class, $this->accountsSession());
        $app->instance(AccountStore::class, $this->accounts);
        $app->instance(DashboardSettings::class, new DashboardSettings(
            new SettingsStore($this->root . '/application.json', new AtomicFile()),
            ['appearance' => ['theme' => 'default', 'button_style' => 'regular']],
        ));
        $app->instance(SecretsStore::class, new SecretsStore(
            $this->root . '/secrets.json',
            new AtomicFile(),
            $appKey,
        ));

        return require dirname(__DIR__, 2) . '/bootstrap/routes.php';
    }

    /** @param array<string, mixed> $overrides */
    private function discussionBody(Session $session, array $overrides = []): array
    {
        return $overrides + [
            'csrf' => $session->csrf(),
            'section' => 'discussion',
            'provider' => 'native',
            'threads_user_id' => '123456789',
            'threads_token_secret' => 'KATAKATA_TEST_THREADS_TOKEN',
        ];
    }

    private function accountsSession(): Session
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        session_id('settings-routes-' . bin2hex(random_bytes(8)));

        $session = new Session($this->accounts);
        $session->login($this->owner);

        return $session;
    }
}

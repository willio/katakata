<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use Katakata\Auth\AccountStore;
use Katakata\Auth\Session;
use Katakata\Dashboard\DashboardSettings;
use Katakata\Editorial\AtomicFile;
use Katakata\Http\Request;
use Katakata\Http\Router;
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

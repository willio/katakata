<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use Katakata\Auth\AccountStore;
use Katakata\Auth\Session;
use Katakata\Content\Repository;
use Katakata\Dashboard\DashboardSettings;
use Katakata\Editorial\AtomicFile;
use Katakata\Editorial\ContentTrash;
use Katakata\Editorial\RevisionStore;
use Katakata\Http\Request;
use Katakata\Settings\SettingsStore;
use PHPUnit\Framework\TestCase;

final class PostsQueryContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-posts-query-' . bin2hex(random_bytes(6));
        foreach (['posts', 'drafts', 'authors', 'assets', 'revisions', 'auth', 'trash'] as $directory) {
            mkdir($this->root . '/' . $directory, 0775, true);
        }
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $this->remove($this->root);
    }

    public function testArrayStatusQueryFallsBackToAllWithoutAWarning(): void
    {
        $router = $this->router();

        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        }, E_WARNING);

        try {
            $response = $router->dispatch(new Request('GET', '/posts', query: ['status' => ['drafts']]));
        } finally {
            restore_error_handler();
        }

        self::assertSame(200, $response->status);
        self::assertSame([], $warnings);
        self::assertStringContainsString('>All</a>', $response->body);
    }

    public function testSearchQueryFiltersTheRenderedIndex(): void
    {
        $router = $this->router();

        $response = $router->dispatch(new Request('GET', '/posts', query: ['q' => 'nothing-matches-this']));

        self::assertSame(200, $response->status);
        self::assertStringContainsString('No posts match “nothing-matches-this”.', $response->body);
    }

    private function router(): \Katakata\Http\Router
    {
        $files = new AtomicFile();
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
        $app->instance(Repository::class, new Repository(
            $this->root . '/posts',
            $this->root . '/drafts',
            $this->root . '/authors',
            $this->root . '/assets',
        ));
        $app->instance(ContentTrash::class, new ContentTrash(
            $this->root . '/trash',
            $files,
            new RevisionStore($this->root . '/revisions', $files),
        ));
        $app->instance(DashboardSettings::class, new DashboardSettings(
            new SettingsStore($this->root . '/application.json', $files),
            ['appearance' => ['theme' => 'default', 'button_style' => 'regular']],
        ));

        return require dirname(__DIR__, 2) . '/bootstrap/routes.php';
    }

    private function remove(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        if (is_file($path)) {
            unlink($path);

            return;
        }
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $child) {
            $this->remove($path . '/' . $child);
        }
        rmdir($path);
    }
}

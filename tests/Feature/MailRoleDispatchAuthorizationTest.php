<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use Katakata\Application;
use Katakata\Auth\AccountStore;
use Katakata\Auth\Session;
use Katakata\Editorial\AtomicFile;
use Katakata\Http\Request;
use Katakata\Http\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MailRoleDispatchAuthorizationTest extends TestCase
{
    private string $root;
    private AccountStore $accounts;
    /** @var array<string, array<string, mixed>> */
    private array $users;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-mail-role-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0700, true);
        $this->accounts = new AccountStore($this->root . '/accounts.json', new AtomicFile());

        $owner = $this->accounts->createOwner('owner@example.test', 'owner-password-123');
        $adminInvite = $this->accounts->invite('admin@example.test', 'admin');
        $editorInvite = $this->accounts->invite('editor@example.test', 'editor');

        $this->users = [
            'owner' => $owner,
            'admin' => $this->accounts->accept($adminInvite['token'], 'admin@example.test', 'admin-password-123'),
            'editor' => $this->accounts->accept($editorInvite['token'], 'editor@example.test', 'editor-password-123'),
        ];
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        @unlink($this->root . '/accounts.json');
        @rmdir($this->root);
    }

    #[DataProvider('privilegedRoles')]
    public function testOwnerAndAdminCanEnterTheMailBoundary(string $role): void
    {
        $router = $this->routerFor($this->users[$role]);
        $response = $router->dispatch(new Request('GET', '/dashboard/mail'));

        self::assertSame(302, $response->status);
        self::assertSame('/mail', $response->headers['Location'] ?? null);
    }

    /** @return iterable<string, array{string}> */
    public static function privilegedRoles(): iterable
    {
        yield 'owner' => ['owner'];
        yield 'admin' => ['admin'];
    }

    #[DataProvider('protectedMailRoutes')]
    public function testEditorIsDeniedByDispatchedMailRoutes(string $method, string $path): void
    {
        $router = $this->routerFor($this->users['editor']);
        $response = $router->dispatch(new Request($method, $path));

        self::assertSame(403, $response->status, $method . ' ' . $path);
        self::assertSame('Forbidden.', $response->body);
    }

    /** @return iterable<string, array{string,string}> */
    public static function protectedMailRoutes(): iterable
    {
        yield 'workspace' => ['GET', '/mail'];
        yield 'legacy redirect' => ['GET', '/dashboard/mail'];
        yield 'sent correspondence' => ['GET', '/mail/sent'];
        yield 'archive' => ['GET', '/mail/archive'];
        yield 'compose' => ['GET', '/mail/compose'];
        yield 'message detail' => ['GET', '/mail/messages/message-1'];
        yield 'account message detail' => ['GET', '/mail/messages/letters/message-1'];
        yield 'account archive message' => ['POST', '/mail/messages/letters/message-1/archive'];
        yield 'account delete message' => ['POST', '/mail/messages/letters/message-1/delete'];
        yield 'archive message' => ['POST', '/mail/messages/message-1/archive'];
        yield 'delete cached message' => ['POST', '/mail/messages/message-1/delete'];
        yield 'draft save' => ['POST', '/mail/drafts/draft-1'];
        yield 'campaign draft' => ['GET', '/mail/campaign-drafts/campaign-1'];
        yield 'campaign autosave' => ['POST', '/mail/campaign-drafts/campaign-1/autosave'];
        yield 'mailbox settings' => ['GET', '/dashboard/settings/mailboxes'];
        yield 'mailbox create' => ['POST', '/dashboard/settings/mailboxes'];
        yield 'mailbox update' => ['POST', '/dashboard/settings/mailboxes/letters'];
        yield 'mailbox enable' => ['POST', '/dashboard/settings/mailboxes/letters/enable'];
        yield 'mailbox disable' => ['POST', '/dashboard/settings/mailboxes/letters/disable'];
        yield 'mailbox delete' => ['POST', '/dashboard/settings/mailboxes/letters/delete'];
        yield 'mailbox import form' => ['GET', '/dashboard/settings/mailboxes/import'];
        yield 'mailbox import upload' => ['POST', '/dashboard/settings/mailboxes/import'];
        yield 'mailbox import confirm' => ['POST', '/dashboard/settings/mailboxes/import/confirm'];
    }

    /** @param array<string, mixed> $account */
    private function routerFor(array $account): Router
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        session_id('mail-role-' . bin2hex(random_bytes(8)));

        $app = new Application(dirname(__DIR__, 2));
        $session = new Session($this->accounts);
        $session->login($account);
        $app->instance(Session::class, $session);
        $router = new Router();

        (static function () use ($app, $router): void {
            require dirname(__DIR__, 2) . '/routes/campaign.php';
            require dirname(__DIR__, 2) . '/routes/mail-accounts.php';
            require dirname(__DIR__, 2) . '/routes/mail.php';
            require dirname(__DIR__, 2) . '/routes/settings-mailboxes.php';
            require dirname(__DIR__, 2) . '/routes/settings-mailbox-import.php';
        })();

        return $router;
    }
}

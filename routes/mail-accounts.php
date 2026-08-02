<?php

declare(strict_types=1);

use Katakata\Auth\Session;
use Katakata\Email\DraftComposer;
use Katakata\Email\Mailbox;
use Katakata\Http\Request;
use Katakata\Http\Response;
use Katakata\View;

/**
 * @var \Katakata\Http\Router $router
 * @var \Katakata\Application $app
 */

$authorizeAccountMail = static function () use ($app): array|Response {
    $session = $app->make(Session::class);
    $user = $session->user();
    if ($user === null) {
        return Response::redirect('/login', 302);
    }
    return $session->canManageMail() ? $user : Response::html('Forbidden.', 403);
};
$validAccountMailCsrf = static fn (Request $request): bool => $app
    ->make(Session::class)
    ->validCsrf($request->body['csrf'] ?? null);
$qualifiedMessageId = static function (string $accountId, string $messageId): ?string {
    if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,31}$/', $accountId)) {
        return null;
    }
    if ($messageId === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $messageId)) {
        return null;
    }
    return $accountId . ':' . $messageId;
};

$router->get('/mail/messages/{accountId}/{messageId}', function (
    Request $request,
    string $accountId,
    string $messageId,
) use ($app, $authorizeAccountMail, $qualifiedMessageId): Response {
    $user = $authorizeAccountMail();
    if ($user instanceof Response) {
        return $user;
    }
    $id = $qualifiedMessageId($accountId, $messageId);
    $message = $id === null ? null : $app->make(Mailbox::class)->message($id);
    if ($message === null) {
        return Response::notFound();
    }

    $view = $app->make(View::class);
    $data = [
        'message' => $message,
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'csrf' => $app->make(Session::class)->csrf(),
    ];
    if (($request->query['fragment'] ?? '') === '1') {
        return Response::html($view->render('mail-message-panel', $data));
    }
    return Response::html($view->render('mail-message', $data));
});

foreach (['read' => true, 'unread' => false] as $action => $read) {
    $router->post('/mail/messages/{accountId}/{messageId}/' . $action, function (
        Request $request,
        string $accountId,
        string $messageId,
    ) use ($app, $authorizeAccountMail, $validAccountMailCsrf, $qualifiedMessageId, $read): Response {
        $user = $authorizeAccountMail();
        if ($user instanceof Response) {
            return $user;
        }
        if (!$validAccountMailCsrf($request)) {
            return Response::html('Invalid CSRF token.', 419);
        }
        $id = $qualifiedMessageId($accountId, $messageId);
        if ($id === null || $app->make(Mailbox::class)->message($id) === null) {
            return Response::notFound();
        }
        $app->make(Mailbox::class)->markRead($id, $read);
        return Response::redirect('/mail?area=inbox&account=' . rawurlencode($accountId) . '&message=' . rawurlencode($messageId), 303);
    });
}

$router->post('/mail/messages/{accountId}/{messageId}/archive', function (
    Request $request,
    string $accountId,
    string $messageId,
) use ($app, $authorizeAccountMail, $validAccountMailCsrf, $qualifiedMessageId): Response {
    $user = $authorizeAccountMail();
    if ($user instanceof Response) {
        return $user;
    }
    if (!$validAccountMailCsrf($request)) {
        return Response::html('Invalid CSRF token.', 419);
    }
    $id = $qualifiedMessageId($accountId, $messageId);
    if ($id === null || $app->make(Mailbox::class)->message($id) === null) {
        return Response::notFound();
    }
    $app->make(Mailbox::class)->archive($id);
    return Response::redirect('/mail/archive', 303);
});

$router->post('/mail/messages/{accountId}/{messageId}/delete', function (
    Request $request,
    string $accountId,
    string $messageId,
) use ($app, $authorizeAccountMail, $validAccountMailCsrf, $qualifiedMessageId): Response {
    $user = $authorizeAccountMail();
    if ($user instanceof Response) {
        return $user;
    }
    if (!$validAccountMailCsrf($request)) {
        return Response::html('Invalid CSRF token.', 419);
    }
    $id = $qualifiedMessageId($accountId, $messageId);
    if ($id === null || $app->make(Mailbox::class)->message($id) === null) {
        return Response::notFound();
    }
    $app->make(Mailbox::class)->deleteLocal($id);
    return Response::redirect('/mail?area=inbox&account=' . rawurlencode($accountId), 303);
});

$router->post('/mail/messages/{accountId}/{messageId}/reply', function (
    Request $request,
    string $accountId,
    string $messageId,
) use ($app, $authorizeAccountMail, $validAccountMailCsrf, $qualifiedMessageId): Response {
    $user = $authorizeAccountMail();
    if ($user instanceof Response) {
        return $user;
    }
    if (!$validAccountMailCsrf($request)) {
        return Response::html('Invalid CSRF token.', 419);
    }
    $id = $qualifiedMessageId($accountId, $messageId);
    $message = $id === null ? null : $app->make(Mailbox::class)->message($id);
    if ($message === null) {
        return Response::notFound();
    }
    $draft = $app->make(DraftComposer::class)->compose(
        $message->from,
        str_starts_with($message->subject, 'Re:') ? $message->subject : 'Re: ' . $message->subject,
        "\n\nOn " . $message->receivedAt->format('M j, Y') . ', ' . $message->from . " wrote:\n> "
            . str_replace("\n", "\n> ", trim($message->text)),
        $message->id,
    );
    return Response::redirect('/mail/drafts/' . rawurlencode($draft->id) . '/edit', 303);
});

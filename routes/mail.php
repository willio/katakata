<?php

declare(strict_types=1);

use Katakata\Auth\Session;
use Katakata\Email\DraftComposer;
use Katakata\Email\DraftSender;
use Katakata\Email\DraftStore;
use Katakata\Email\MailComposeViewModel;
use Katakata\Email\Mailbox;
use Katakata\Http\Request;
use Katakata\Http\Response;
use Katakata\View;

/**
 * @var \Katakata\Http\Router $router
 * @var \Katakata\Application $app
 */

$requireMailUser = static function () use ($app): ?array {
    return $app->make(Session::class)->user();
};

$validMailCsrf = static function (Request $request) use ($app): bool {
    return $app->make(Session::class)->validCsrf($request->body['csrf'] ?? null);
};

$renderCompose = static function (\Katakata\Email\Draft $draft, ?string $notice = null) use ($app): Response {
    return Response::html($app->make(View::class)->render('mail-compose', [
        'compose' => MailComposeViewModel::fromDraft($draft),
        'csrf' => $app->make(Session::class)->csrf(),
        'notice' => $notice,
    ]));
};

$recipients = static function (mixed $value): array {
    if (!is_string($value)) {
        return [];
    }

    return array_values(array_filter(array_map(
        static fn (string $email): string => trim($email),
        preg_split('/[,;\n]+/', $value) ?: [],
    )));
};

$router->get('/dashboard/mail', function (Request $request) use ($app, $requireMailUser): Response {
    if ($requireMailUser() === null) {
        return Response::redirect('/login', 302);
    }

    $query = trim((string) ($request->query['q'] ?? ''));
    $mailbox = $app->make(Mailbox::class);

    return Response::html($app->make(View::class)->render('mail-inbox', [
        'messages' => $query === '' ? $mailbox->inbox() : $mailbox->search($query),
        'drafts' => $app->make(DraftStore::class)->recent(8),
        'query' => $query,
        'csrf' => $app->make(Session::class)->csrf(),
    ]));
});

$router->get('/dashboard/mail/search', function (Request $request): Response {
    $query = trim((string) ($request->query['q'] ?? ''));
    return Response::redirect('/dashboard/mail' . ($query === '' ? '' : '?q=' . rawurlencode($query)), 302);
});

$router->get('/dashboard/mail/messages/{id}', function (Request $request, string $id) use ($app, $requireMailUser): Response {
    if ($requireMailUser() === null) {
        return Response::redirect('/login', 302);
    }

    $mailbox = $app->make(Mailbox::class);
    $message = $mailbox->message($id);
    if ($message === null) {
        return Response::notFound();
    }

    if (!$message->isRead) {
        $mailbox->markRead($id, true);
        $message = $mailbox->message($id) ?? $message;
    }

    return Response::html($app->make(View::class)->render('mail-message', [
        'message' => $message,
        'csrf' => $app->make(Session::class)->csrf(),
    ]));
});

foreach (['read' => true, 'unread' => false] as $action => $read) {
    $router->post('/dashboard/mail/messages/{id}/' . $action, function (Request $request, string $id) use ($app, $requireMailUser, $validMailCsrf, $read): Response {
        if ($requireMailUser() === null) {
            return Response::redirect('/login', 302);
        }
        if (!$validMailCsrf($request)) {
            return Response::html('Invalid CSRF token.', 419);
        }

        $app->make(Mailbox::class)->markRead($id, $read);
        return Response::redirect('/dashboard/mail/messages/' . rawurlencode($id), 302);
    });
}

$router->post('/dashboard/mail/messages/{id}/archive', function (Request $request, string $id) use ($app, $requireMailUser, $validMailCsrf): Response {
    if ($requireMailUser() === null) {
        return Response::redirect('/login', 302);
    }
    if (!$validMailCsrf($request)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    $app->make(Mailbox::class)->archive($id);
    return Response::redirect('/dashboard/mail', 302);
});

$router->get('/dashboard/mail/compose', function (Request $request) use ($app, $requireMailUser, $renderCompose): Response {
    if ($requireMailUser() === null) {
        return Response::redirect('/login', 302);
    }

    return $renderCompose($app->make(DraftComposer::class)->compose());
});

$router->post('/dashboard/mail/messages/{id}/reply', function (Request $request, string $id) use ($app, $requireMailUser, $validMailCsrf): Response {
    if ($requireMailUser() === null) {
        return Response::redirect('/login', 302);
    }
    if (!$validMailCsrf($request)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    $message = $app->make(Mailbox::class)->message($id);
    if ($message === null) {
        return Response::notFound();
    }

    $draft = $app->make(DraftComposer::class)->reply($message);
    return Response::redirect('/dashboard/mail/drafts/' . rawurlencode($draft->id), 302);
});

$router->get('/dashboard/mail/drafts/{id}', function (Request $request, string $id) use ($app, $requireMailUser, $renderCompose): Response {
    if ($requireMailUser() === null) {
        return Response::redirect('/login', 302);
    }

    $draft = $app->make(DraftStore::class)->find($id);
    return $draft === null ? Response::notFound() : $renderCompose($draft);
});

$router->post('/dashboard/mail/drafts/{id}', function (Request $request, string $id) use ($app, $requireMailUser, $validMailCsrf, $recipients): Response {
    if ($requireMailUser() === null) {
        return Response::json(['error' => 'Authentication required.'], 401);
    }
    if (!$validMailCsrf($request)) {
        return Response::json(['error' => 'The editor session expired.'], 419);
    }

    try {
        $draft = $app->make(DraftComposer::class)->update(
            $id,
            $recipients($request->body['recipients'] ?? ''),
            (string) ($request->body['subject'] ?? ''),
            (string) ($request->body['body'] ?? ''),
        );

        if (($request->body['intent'] ?? '') === 'send') {
            $app->make(DraftSender::class)->send($draft->id);
            return Response::redirect('/dashboard/mail?sent=1', 302);
        }

        if (($request->header('accept') ?? '') === 'application/json') {
            return Response::json([
                'saved' => true,
                'updated_at' => $draft->updatedAt->format(DATE_ATOM),
                'client_version' => $request->body['client_version'] ?? '',
            ]);
        }

        return Response::redirect('/dashboard/mail/drafts/' . rawurlencode($draft->id), 302);
    } catch (\Throwable $error) {
        return Response::json(['error' => $error->getMessage()], 422);
    }
});

$router->post('/dashboard/mail/send', function (Request $request) use ($app, $requireMailUser, $validMailCsrf, $recipients): Response {
    if ($requireMailUser() === null) {
        return Response::redirect('/login', 302);
    }
    if (!$validMailCsrf($request)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    try {
        $id = (string) ($request->body['draft_id'] ?? '');
        $app->make(DraftComposer::class)->update(
            $id,
            $recipients($request->body['recipients'] ?? ''),
            (string) ($request->body['subject'] ?? ''),
            (string) ($request->body['body'] ?? ''),
        );
        $app->make(DraftSender::class)->send($id);
        return Response::redirect('/dashboard/mail?sent=1', 302);
    } catch (\Throwable $error) {
        return Response::html($error->getMessage(), 422);
    }
});

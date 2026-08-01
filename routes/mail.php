<?php

declare(strict_types=1);

use Katakata\Auth\Session;
use Katakata\Email\DraftComposer;
use Katakata\Email\DraftSender;
use Katakata\Email\DraftStore;
use Katakata\Email\Mailbox;
use Katakata\Http\Request;
use Katakata\Http\Response;
use Katakata\View;

/**
 * @var \Katakata\Http\Router $router
 * @var \Katakata\Application $app
 */

$authorizeMail = $authorizeMail ?? static function () use ($app): array|Response {
    $session = $app->make(Session::class);
    $user = $session->user();
    if ($user === null) {
        return Response::redirect('/login', 302);
    }
    if (!$session->canManageMail()) {
        return Response::html('Forbidden.', 403);
    }

    return $user;
};

$validMailCsrf = static function (Request $request) use ($app): bool {
    return $app->make(Session::class)->validCsrf($request->body['csrf'] ?? null);
};

$router->get('/dashboard/mail', function (Request $request) use ($authorizeMail): Response {
    $user = $authorizeMail();
    return $user instanceof Response ? $user : Response::redirect('/mail', 302);
});

$router->get('/mail/messages/{id}', function (Request $request, string $id) use ($app, $authorizeMail): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) {
        return $user;
    }

    $message = $app->make(Mailbox::class)->message($id);
    if ($message === null) {
        return Response::notFound();
    }

    return Response::html($app->make(View::class)->render('mail-message', [
        'message' => $message,
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'csrf' => $app->make(Session::class)->csrf(),
    ]));
});

foreach (['read' => true, 'unread' => false] as $action => $read) {
    $router->post('/mail/messages/{id}/' . $action, function (Request $request, string $id) use ($app, $authorizeMail, $validMailCsrf, $read): Response {
        $user = $authorizeMail();
        if ($user instanceof Response) {
            return $user;
        }
        if (!$validMailCsrf($request)) {
            return Response::html('Invalid CSRF token.', 419);
        }

        $app->make(Mailbox::class)->markRead($id, $read);
        return Response::redirect('/mail/messages/' . rawurlencode($id), 303);
    });
}

$router->post('/mail/messages/{id}/archive', function (Request $request, string $id) use ($app, $authorizeMail, $validMailCsrf): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) {
        return $user;
    }
    if (!$validMailCsrf($request)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    $app->make(Mailbox::class)->archive($id);
    return Response::redirect('/mail?area=inbox', 303);
});

$router->get('/mail/messages/{messageId}/attachments/{attachmentId}', function (Request $request, string $messageId, string $attachmentId) use ($app, $authorizeMail): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) {
        return $user;
    }

    $download = $app->make(Mailbox::class)->attachment($messageId, $attachmentId);
    if ($download === null) {
        return Response::notFound();
    }

    return new Response($download->content, 200, [
        'Content-Type' => $download->mediaType,
        'Content-Disposition' => 'attachment; filename="' . addcslashes($download->name, "\\\"") . '"',
        'X-Content-Type-Options' => 'nosniff',
    ]);
});

$router->get('/mail/compose', function (Request $request) use ($app, $authorizeMail): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) {
        return $user;
    }

    $draft = $app->make(DraftComposer::class)->compose('', '', '');
    return Response::redirect('/mail/drafts/' . rawurlencode($draft->id), 302);
});

$router->post('/mail/messages/{id}/reply', function (Request $request, string $id) use ($app, $authorizeMail, $validMailCsrf): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) {
        return $user;
    }
    if (!$validMailCsrf($request)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    $message = $app->make(Mailbox::class)->message($id);
    if ($message === null) {
        return Response::notFound();
    }

    $draft = $app->make(DraftComposer::class)->compose(
        $message->from,
        str_starts_with($message->subject, 'Re:') ? $message->subject : 'Re: ' . $message->subject,
        "\n\nOn " . $message->receivedAt->format('M j, Y') . ', ' . $message->from . " wrote:\n> " . str_replace("\n", "\n> ", trim($message->text)),
        $message->id,
    );

    return Response::redirect('/mail/drafts/' . rawurlencode($draft->id), 303);
});

$router->get('/mail/drafts/{id}', function (Request $request, string $id) use ($app, $authorizeMail): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) {
        return $user;
    }

    $draft = $app->make(DraftStore::class)->find($id);
    if ($draft === null) {
        return Response::notFound();
    }

    return Response::html($app->make(View::class)->render('mail-compose', [
        'draft' => $draft,
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'csrf' => $app->make(Session::class)->csrf(),
        'error' => null,
    ]));
});

$router->post('/mail/drafts/{id}', function (Request $request, string $id) use ($app, $authorizeMail, $validMailCsrf): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) {
        return $user;
    }
    if (!$validMailCsrf($request)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    $existing = $app->make(DraftStore::class)->find($id);
    if ($existing === null) {
        return Response::notFound();
    }

    $draft = new \Katakata\Email\Draft(
        id: $existing->id,
        to: trim((string) ($request->body['to'] ?? '')),
        subject: trim((string) ($request->body['subject'] ?? '')),
        text: (string) ($request->body['text'] ?? ''),
        inReplyTo: $existing->inReplyTo,
        updatedAt: new DateTimeImmutable(),
    );
    $app->make(DraftStore::class)->save($draft);

    if (($request->body['intent'] ?? '') === 'send') {
        try {
            $app->make(DraftSender::class)->send($draft->id);
            return Response::redirect('/mail?area=inbox&sent=1', 303);
        } catch (\Throwable $error) {
            return Response::html($app->make(View::class)->render('mail-compose', [
                'draft' => $draft,
                'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
                'csrf' => $app->make(Session::class)->csrf(),
                'error' => $error->getMessage(),
            ]), 422);
        }
    }

    return Response::redirect('/mail/drafts/' . rawurlencode($draft->id) . '?saved=1', 303);
});

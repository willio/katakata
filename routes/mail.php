<?php

declare(strict_types=1);

use Katakata\Auth\Session;
use Katakata\Dashboard\DashboardSettings;
use Katakata\Email\Draft;
use Katakata\Email\DraftComposer;
use Katakata\Email\DraftConflict;
use Katakata\Email\DraftSender;
use Katakata\Email\DraftStore;
use Katakata\Email\Mailbox;
use Katakata\Email\SentMessageStore;
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
    if ($user === null) return Response::redirect('/login', 302);
    if (!$session->canManageMail()) return Response::html('Forbidden.', 403);
    return $user;
};

$validMailCsrf = static function (Request $request) use ($app): bool {
    return $app->make(Session::class)->validCsrf($request->body['csrf'] ?? null);
};

$mailButtonStyle = static function () use ($app): string {
    return (string) ($app->make(DashboardSettings::class)->section('appearance')['button_style'] ?? 'regular');
};

$draftPayload = static fn (Draft $draft): array => [
    'id' => $draft->id,
    'to' => $draft->to,
    'subject' => $draft->subject,
    'text' => $draft->text,
    'version' => $draft->version,
    'created_at' => $draft->createdAt->format(DATE_ATOM),
    'updated_at' => $draft->updatedAt->format(DATE_ATOM),
];

$renderDraftEditor = static function (array $user, Draft $draft, ?string $error = null) use ($app, $mailButtonStyle): Response {
    return Response::html($app->make(View::class)->render('mail-draft-editor', [
        'user' => $user,
        'draft' => $draft,
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'buttonStyle' => $mailButtonStyle(),
        'csrf' => $app->make(Session::class)->csrf(),
        'error' => $error,
    ]), $error === null ? 200 : 422);
};

$router->get('/dashboard/mail', function (Request $request) use ($authorizeMail): Response {
    $user = $authorizeMail();
    return $user instanceof Response ? $user : Response::redirect('/mail', 302);
});

$router->get('/mail/sent', function (Request $request) use ($app, $authorizeMail, $mailButtonStyle): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) return $user;
    return Response::html($app->make(View::class)->render('mail-sent', [
        'user' => $user,
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'buttonStyle' => $mailButtonStyle(),
        'messages' => $app->make(SentMessageStore::class)->recent(),
        'csrf' => $app->make(Session::class)->csrf(),
    ]));
});

$router->get('/mail/archive', function (Request $request) use ($app, $authorizeMail, $mailButtonStyle): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) return $user;
    $mailbox = $app->make(Mailbox::class);
    return Response::html($app->make(View::class)->render('mail-archive', [
        'user' => $user,
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'buttonStyle' => $mailButtonStyle(),
        'messages' => $mailbox->archived(),
        'mailboxReadiness' => $mailbox->readiness(),
        'csrf' => $app->make(Session::class)->csrf(),
    ]));
});

$router->get('/mail/messages/{id}', function (Request $request, string $id) use ($app, $authorizeMail, $mailButtonStyle): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) return $user;
    $message = $app->make(Mailbox::class)->message($id);
    if ($message === null) return Response::notFound();
    return Response::html($app->make(View::class)->render('mail-message', [
        'message' => $message,
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'buttonStyle' => $mailButtonStyle(),
        'csrf' => $app->make(Session::class)->csrf(),
    ]));
});

foreach (['read' => true, 'unread' => false] as $action => $read) {
    $router->post('/mail/messages/{id}/' . $action, function (Request $request, string $id) use ($app, $authorizeMail, $validMailCsrf, $read): Response {
        $user = $authorizeMail();
        if ($user instanceof Response) return $user;
        if (!$validMailCsrf($request)) return Response::html('Invalid CSRF token.', 419);
        $app->make(Mailbox::class)->markRead($id, $read);
        return Response::redirect('/mail/messages/' . rawurlencode($id), 303);
    });
}

$router->post('/mail/messages/{id}/archive', function (Request $request, string $id) use ($app, $authorizeMail, $validMailCsrf): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) return $user;
    if (!$validMailCsrf($request)) return Response::html('Invalid CSRF token.', 419);
    $app->make(Mailbox::class)->archive($id);
    return Response::redirect('/mail/archive', 303);
});

$router->post('/mail/messages/{id}/delete', function (Request $request, string $id) use ($app, $authorizeMail, $validMailCsrf): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) return $user;
    if (!$validMailCsrf($request)) return Response::html('Invalid CSRF token.', 419);
    $app->make(Mailbox::class)->deleteLocal($id);
    return Response::redirect('/mail?area=inbox', 303);
});

$router->get('/mail/compose', function (Request $request) use ($app, $authorizeMail): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) return $user;
    $draft = $app->make(DraftComposer::class)->compose('', '', '');
    return Response::redirect('/mail/drafts/' . rawurlencode($draft->id) . '/edit', 302);
});

$router->post('/mail/messages/{id}/reply', function (Request $request, string $id) use ($app, $authorizeMail, $validMailCsrf): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) return $user;
    if (!$validMailCsrf($request)) return Response::html('Invalid CSRF token.', 419);
    $message = $app->make(Mailbox::class)->message($id);
    if ($message === null) return Response::notFound();
    $draft = $app->make(DraftComposer::class)->compose(
        $message->from,
        str_starts_with($message->subject, 'Re:') ? $message->subject : 'Re: ' . $message->subject,
        "\n\nOn " . $message->receivedAt->format('M j, Y') . ', ' . $message->from . " wrote:\n> " . str_replace("\n", "\n> ", trim($message->text)),
        $message->id,
    );
    return Response::redirect('/mail/drafts/' . rawurlencode($draft->id) . '/edit', 303);
});

$router->get('/mail/drafts/{id}', function (Request $request, string $id) use ($app, $authorizeMail): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) return $user;
    $draft = $app->make(DraftStore::class)->find($id);
    return $draft === null ? Response::notFound() : Response::redirect('/mail/drafts/' . rawurlencode($draft->id) . '/edit', 302);
});

$router->get('/mail/drafts/{id}/edit', function (Request $request, string $id) use ($app, $authorizeMail, $renderDraftEditor): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) return $user;
    $draft = $app->make(DraftStore::class)->find($id);
    return $draft === null ? Response::notFound() : $renderDraftEditor($user, $draft, $request->query['error'] ?? null);
});

$router->post('/mail/drafts/{id}/autosave', function (Request $request, string $id) use ($app, $authorizeMail, $validMailCsrf, $draftPayload): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) return $user;
    if (!$validMailCsrf($request)) return Response::json(['error' => 'Invalid CSRF token.'], 419);

    $store = $app->make(DraftStore::class);
    $existing = $store->find($id);
    if ($existing === null) return Response::json(['error' => 'Draft not found.'], 404);

    $next = new Draft(
        id: $existing->id,
        to: trim((string) ($request->body['to'] ?? '')),
        subject: trim((string) ($request->body['subject'] ?? '')),
        text: (string) ($request->body['text'] ?? ''),
        inReplyTo: $existing->inReplyTo,
        version: $existing->version,
        createdAt: $existing->createdAt,
        updatedAt: new DateTimeImmutable(),
    );

    try {
        $saved = $store->save($next, (int) ($request->body['expected_version'] ?? 0));
        return Response::json([
            'saved' => true,
            'id' => $saved->id,
            'version' => $saved->version,
            'updated_at' => $saved->updatedAt->format(DATE_ATOM),
            'client_version' => (string) ($request->body['client_version'] ?? ''),
        ]);
    } catch (DraftConflict $conflict) {
        return Response::json([
            'error' => $conflict->getMessage(),
            'current' => $draftPayload($conflict->current),
            'client_version' => (string) ($request->body['client_version'] ?? ''),
        ], 409);
    }
});

$router->post('/mail/drafts/{id}', function (Request $request, string $id) use ($app, $authorizeMail, $validMailCsrf, $renderDraftEditor): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) return $user;
    if (!$validMailCsrf($request)) return Response::html('Invalid CSRF token.', 419);

    $store = $app->make(DraftStore::class);
    $existing = $store->find($id);
    if ($existing === null) return Response::notFound();

    $next = new Draft(
        id: $existing->id,
        to: trim((string) ($request->body['to'] ?? '')),
        subject: trim((string) ($request->body['subject'] ?? '')),
        text: (string) ($request->body['text'] ?? ''),
        inReplyTo: $existing->inReplyTo,
        version: $existing->version,
        createdAt: $existing->createdAt,
        updatedAt: new DateTimeImmutable(),
    );

    try {
        $saved = $store->save($next, (int) ($request->body['expected_version'] ?? 0));
    } catch (DraftConflict $conflict) {
        return $renderDraftEditor($user, $conflict->current, $conflict->getMessage());
    }

    if (($request->body['intent'] ?? '') === 'send') {
        try {
            $app->make(DraftSender::class)->send($saved->id);
            return Response::redirect('/mail/sent', 303);
        } catch (\Throwable $error) {
            return $renderDraftEditor($user, $saved, $error->getMessage());
        }
    }
    return Response::redirect('/mail/drafts/' . rawurlencode($saved->id) . '/edit?saved=1', 303);
});

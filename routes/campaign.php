<?php

declare(strict_types=1);

use Katakata\Auth\Session;
use Katakata\Distribution\SubscriberStore;
use Katakata\Email\DraftStore;
use Katakata\Email\Mailbox;
use Katakata\Email\MailboxRefreshRequest;
use Katakata\Http\Request;
use Katakata\Http\Response;
use Katakata\Mail\CampaignDispatcher;
use Katakata\Mail\CampaignDraft;
use Katakata\Mail\CampaignDraftConflict;
use Katakata\Mail\CampaignDraftFactory;
use Katakata\Mail\CampaignDraftReviewer;
use Katakata\Mail\CampaignDraftStore;
use Katakata\Mail\CampaignRetryService;
use Katakata\Mail\CampaignStatus;
use Katakata\Mail\CampaignStore;
use Katakata\Mail\MailAttention;
use Katakata\Mail\MailWorkspace;
use Katakata\View;

/**
 * @var \Katakata\Http\Router $router
 * @var \Katakata\Application $app
 */

$authorizeMail = static function () use ($app): array|Response {
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

$router->get('/mail', function (Request $request) use ($app, $authorizeMail): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) {
        return $user;
    }

    $workspace = $app->make(MailWorkspace::class);
    $mailbox = $app->make(Mailbox::class);
    $attention = $app->make(MailAttention::class);
    $area = trim((string) ($request->query['area'] ?? $attention->landing()));
    if (!in_array($area, ['inbox', 'campaigns'], true)) {
        $area = $attention->landing();
    }

    return Response::html($app->make(View::class)->render('mail', [
        'user' => $user,
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'area' => $area,
        'attention' => $attention->summary(),
        'mailboxReadiness' => $mailbox->readiness(),
        'messages' => $mailbox->inbox(),
        'drafts' => $app->make(DraftStore::class)->recent(),
        'campaignDrafts' => $app->make(CampaignDraftStore::class)->recent(),
        'queue' => $workspace->reviewQueue(),
        'audience' => $workspace->recipientPreview(),
        'campaign' => $workspace->campaignPreview($request->query['post'] ?? ''),
        'newsletterReady' => !($app->make(SubscriberStore::class) instanceof \Katakata\Distribution\UnavailableSubscriberStore),
        'refreshRequested' => $area === 'inbox' && ($request->query['refresh'] ?? '') === 'requested',
        'csrf' => $app->make(Session::class)->csrf(),
    ]));
});

$router->post('/mail/refresh', function (Request $request) use ($app, $authorizeMail): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) {
        return $user;
    }
    if (!$app->make(Session::class)->validCsrf($request->body['csrf'] ?? null)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    $app->make(MailboxRefreshRequest::class)->request();
    return Response::redirect('/mail?area=inbox&refresh=requested', 303);
});

$router->get('/mail/campaign-drafts/{id}', function (Request $request, string $id) use ($app, $authorizeMail): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) {
        return $user;
    }

    $draft = $app->make(CampaignDraftStore::class)->find($id);
    if ($draft === null) {
        return Response::notFound();
    }

    $review = null;
    if (($request->query['review'] ?? '') === '1') {
        $review = $app->make(CampaignDraftReviewer::class)->review($draft);
    }

    return Response::html($app->make(View::class)->render('mail-campaign-draft', [
        'draft' => $draft,
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'csrf' => $app->make(Session::class)->csrf(),
        'review' => $review,
        'error' => trim((string) ($request->query['error'] ?? '')),
    ]));
});

$router->post('/mail/campaign-drafts/{id}/autosave', function (Request $request, string $id) use ($app, $authorizeMail): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) {
        return $user;
    }

    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::json(['error' => 'The campaign draft session expired.'], 419);
    }

    $current = $app->make(CampaignDraftStore::class)->find($id);
    if ($current === null) {
        return Response::json(['error' => 'Campaign draft not found.'], 404);
    }
    if ($current->isConfirmed()) {
        return Response::json([
            'error' => 'Campaign draft is already confirmed.',
            'campaign_id' => $current->confirmedCampaignId,
        ], 409);
    }

    $incoming = new CampaignDraft(
        id: $current->id,
        subject: trim((string) ($request->body['subject'] ?? $current->subject)),
        preheader: trim((string) ($request->body['preheader'] ?? $current->preheader)),
        body: (string) ($request->body['body'] ?? $current->body),
        version: $current->version,
        createdAt: $current->createdAt,
        updatedAt: new \DateTimeImmutable(),
        createdBy: $current->createdBy,
        sourceType: $current->sourceType,
        sourceId: $current->sourceId,
        sourceRevision: $current->sourceRevision,
        sourceHash: $current->sourceHash,
        sourceCreatedAt: $current->sourceCreatedAt,
    );

    try {
        $saved = $app->make(CampaignDraftStore::class)->save(
            $incoming,
            max(1, (int) ($request->body['expected_version'] ?? 0)),
        );
    } catch (CampaignDraftConflict $conflict) {
        return Response::json([
            'error' => 'Campaign draft changed elsewhere.',
            'current' => $conflict->current->toArray(),
            'client_version' => (string) ($request->body['client_version'] ?? ''),
        ], 409);
    } catch (\Throwable $error) {
        return Response::json(['error' => $error->getMessage()], 422);
    }

    return Response::json([
        'saved' => true,
        'id' => $saved->id,
        'version' => $saved->version,
        'updated_at' => $saved->updatedAt->format(DATE_ATOM),
        'client_version' => (string) ($request->body['client_version'] ?? ''),
    ]);
});

// Remaining campaign routes retain their existing implementation below this slice.

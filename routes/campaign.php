<?php

declare(strict_types=1);

use DateTimeImmutable;
use Katakata\Auth\Session;
use Katakata\Distribution\SubscriberStore;
use Katakata\Email\DraftStore;
use Katakata\Email\Mailbox;
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
        'csrf' => $app->make(Session::class)->csrf(),
    ]));
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

    $incoming = new CampaignDraft(
        id: $current->id,
        subject: trim((string) ($request->body['subject'] ?? $current->subject)),
        preheader: trim((string) ($request->body['preheader'] ?? $current->preheader)),
        body: (string) ($request->body['body'] ?? $current->body),
        version: $current->version,
        createdAt: $current->createdAt,
        updatedAt: new DateTimeImmutable(),
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

$router->post('/mail/campaign-drafts/{id}', function (Request $request, string $id) use ($app, $authorizeMail): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) {
        return $user;
    }

    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    $current = $app->make(CampaignDraftStore::class)->find($id);
    if ($current === null) {
        return Response::notFound();
    }

    $incoming = new CampaignDraft(
        id: $current->id,
        subject: trim((string) ($request->body['subject'] ?? $current->subject)),
        preheader: trim((string) ($request->body['preheader'] ?? $current->preheader)),
        body: (string) ($request->body['body'] ?? $current->body),
        version: $current->version,
        createdAt: $current->createdAt,
        updatedAt: new DateTimeImmutable(),
        createdBy: $current->createdBy,
        sourceType: $current->sourceType,
        sourceId: $current->sourceId,
        sourceRevision: $current->sourceRevision,
        sourceHash: $current->sourceHash,
        sourceCreatedAt: $current->sourceCreatedAt,
    );

    try {
        $app->make(CampaignDraftStore::class)->save(
            $incoming,
            max(1, (int) ($request->body['expected_version'] ?? 0)),
        );
    } catch (CampaignDraftConflict) {
        return Response::redirect('/mail/campaign-drafts/' . rawurlencode($id) . '?conflict=1', 303);
    } catch (\Throwable) {
        return Response::redirect('/mail/campaign-drafts/' . rawurlencode($id) . '?error=save', 303);
    }

    $query = ($request->body['intent'] ?? '') === 'review' ? '?review=1' : '?saved=1';
    return Response::redirect('/mail/campaign-drafts/' . rawurlencode($id) . $query, 303);
});

$router->post('/mail/campaign-drafts/{id}/confirm', function (Request $request, string $id) use ($app, $authorizeMail): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) {
        return $user;
    }

    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    $draft = $app->make(CampaignDraftStore::class)->find($id);
    if ($draft === null) {
        return Response::notFound();
    }
    if ($draft->version !== max(1, (int) ($request->body['expected_version'] ?? 0))) {
        return Response::redirect('/mail/campaign-drafts/' . rawurlencode($id) . '?conflict=1', 303);
    }

    try {
        $campaign = $app->make(CampaignDispatcher::class)->confirmDraftAndQueue(
            $draft,
            $app->make(CampaignDraftReviewer::class),
        );
    } catch (\Throwable) {
        return Response::redirect('/mail/campaign-drafts/' . rawurlencode($id) . '?review=1&error=confirm', 303);
    }

    return Response::redirect('/mail/campaign/' . rawurlencode($campaign->id), 303);
});

$router->post('/mail/campaign/{id}/drafts', function (Request $request, string $id) use ($app, $authorizeMail): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) {
        return $user;
    }

    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    $campaign = $app->make(CampaignStore::class)->find($id);
    if ($campaign === null) {
        return Response::notFound();
    }

    $actor = (string) ($user['email'] ?? $user['id'] ?? 'owner');
    $draft = $app->make(CampaignDraftFactory::class)->fromCampaign($campaign, $actor);
    $app->make(CampaignDraftStore::class)->create($draft);

    return Response::redirect('/mail/campaign-drafts/' . rawurlencode($draft->id), 303);
});

$router->get('/mail/campaigns', function (Request $request) use ($app, $authorizeMail): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) {
        return $user;
    }

    $status = $app->make(CampaignStatus::class);
    $campaigns = array_map(
        static fn ($campaign): array => [
            'campaign' => $campaign,
            'delivery' => $status->summarize($campaign),
        ],
        $app->make(CampaignStore::class)->all(),
    );

    return Response::html($app->make(View::class)->render('mail-campaigns', [
        'user' => $user,
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'campaigns' => $campaigns,
        'csrf' => $app->make(Session::class)->csrf(),
    ]));
});

$router->get('/mail/confirm', function (Request $request) use ($app, $authorizeMail): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) {
        return $user;
    }

    $proof = $app->make(MailWorkspace::class)->dispatchProof($request->query['post'] ?? '');
    if ($proof === null) {
        return Response::redirect('/mail?area=campaigns', 302);
    }

    return Response::html($app->make(View::class)->render('mail-confirm', [
        'user' => $user,
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'proof' => $proof,
        'csrf' => $app->make(Session::class)->csrf(),
    ]));
});

$router->post('/mail/confirm', function (Request $request) use ($app, $authorizeMail): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) {
        return $user;
    }

    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::redirect('/mail?area=campaigns', 302);
    }

    try {
        $campaign = $app->make(CampaignDispatcher::class)->confirmAndQueue($request->body['post'] ?? '');
        return Response::redirect('/mail/campaign/' . rawurlencode($campaign->id), 303);
    } catch (\Throwable) {
        return Response::redirect('/mail?area=campaigns', 302);
    }
});

$router->post('/mail/campaign/{id}/retry', function (Request $request, string $id) use ($app, $authorizeMail): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) {
        return $user;
    }

    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::redirect('/mail/campaign/' . rawurlencode($id), 302);
    }

    try {
        $campaign = $app->make(CampaignStore::class)->find($id);
        if ($campaign === null) {
            return Response::notFound();
        }
        $app->make(CampaignRetryService::class)->retry($campaign);
    } catch (\Throwable) {
        return Response::redirect('/mail/campaign/' . rawurlencode($id), 302);
    }

    return Response::redirect('/mail/campaign/' . rawurlencode($id), 303);
});

$router->get('/mail/campaign/{id}', function (Request $request, string $id) use ($app, $authorizeMail): Response {
    $user = $authorizeMail();
    if ($user instanceof Response) {
        return $user;
    }

    try {
        $campaign = $app->make(CampaignStore::class)->find($id);
    } catch (\Throwable) {
        $campaign = null;
    }

    if ($campaign === null) {
        return Response::notFound();
    }

    return Response::html($app->make(View::class)->render('mail-campaign', [
        'user' => $user,
        'siteName' => (string) $app->config()->get('app.name', 'Katakata'),
        'campaign' => $campaign,
        'delivery' => $app->make(CampaignStatus::class)->summarize($campaign),
        'csrf' => $app->make(Session::class)->csrf(),
    ]));
});

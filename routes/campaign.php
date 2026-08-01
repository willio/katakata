<?php

declare(strict_types=1);

use Katakata\Auth\Session;
use Katakata\Email\Mailbox;
use Katakata\Http\Request;
use Katakata\Http\Response;
use Katakata\Mail\CampaignDispatcher;
use Katakata\Mail\CampaignRetryService;
use Katakata\Mail\CampaignStatus;
use Katakata\Mail\CampaignStore;
use Katakata\Mail\MailAttention;
use Katakata\Mail\MailWorkspace;
use Katakata\View;

/**
 * @var \Katakata\Http\Router $router
 * @var \Katakata\Application $app
 * @var callable(): ?array $requireUser
 */

$router->get('/mail', function (Request $request) use ($app, $requireUser): Response {
    $user = $requireUser();
    if ($user === null) {
        return Response::redirect('/login', 302);
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
        'queue' => $workspace->reviewQueue(),
        'audience' => $workspace->recipientPreview(),
        'campaign' => $workspace->campaignPreview($request->query['post'] ?? ''),
        'csrf' => $app->make(Session::class)->csrf(),
    ]));
});

$router->get('/mail/campaigns', function (Request $request) use ($app, $requireUser): Response {
    $user = $requireUser();
    if ($user === null) {
        return Response::redirect('/login', 302);
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

$router->get('/mail/confirm', function (Request $request) use ($app, $requireUser): Response {
    $user = $requireUser();
    if ($user === null) {
        return Response::redirect('/login', 302);
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

$router->post('/mail/confirm', function (Request $request) use ($app, $requireUser): Response {
    if ($requireUser() === null) {
        return Response::redirect('/login', 302);
    }

    $session = $app->make(Session::class);
    if (!$session->validCsrf($request->body['csrf'] ?? null)) {
        return Response::redirect('/mail?area=campaigns', 302);
    }

    try {
        $campaign = $app->make(CampaignDispatcher::class)->confirmAndQueue(
            $request->body['post'] ?? '',
        );
        return Response::redirect('/mail/campaign/' . rawurlencode($campaign->id), 303);
    } catch (\Throwable) {
        return Response::redirect('/mail?area=campaigns', 302);
    }
});

$router->post('/mail/campaign/{id}/retry', function (Request $request, string $id) use ($app, $requireUser): Response {
    if ($requireUser() === null) {
        return Response::redirect('/login', 302);
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

$router->get('/mail/campaign/{id}', function (Request $request, string $id) use ($app, $requireUser): Response {
    $user = $requireUser();
    if ($user === null) {
        return Response::redirect('/login', 302);
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

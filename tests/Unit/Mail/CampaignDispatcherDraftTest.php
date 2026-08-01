<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Mail;

use DateTimeImmutable;
use Katakata\Content\Repository;
use Katakata\Distribution\EmailMessage;
use Katakata\Distribution\EmailTransport;
use Katakata\Distribution\MailQueue;
use Katakata\Distribution\SubscriberStore;
use Katakata\Editorial\AtomicFile;
use Katakata\Mail\CampaignDispatcher;
use Katakata\Mail\CampaignDraft;
use Katakata\Mail\CampaignDraftReviewer;
use Katakata\Mail\CampaignStore;
use Katakata\Mail\MailWorkspace;
use Katakata\Rendering\Markdown;
use PHPUnit\Framework\TestCase;

final class CampaignDispatcherDraftTest extends TestCase
{
    public function testConfirmationSnapshotsCurrentRecipientsAndQueueEntries(): void
    {
        $root = sys_get_temp_dir() . '/katakata-campaign-dispatch-' . bin2hex(random_bytes(6));
        mkdir($root, 0775, true);

        $files = new AtomicFile();
        $subscriberPath = $root . '/subscribers.json';
        $subscriberStore = new SubscriberStore($subscriberPath, 'test-secret', $files);

        $first = $subscriberStore->request('first@example.com', new DateTimeImmutable('2026-08-01T10:00:00+00:00'));
        $subscriberStore->confirm($first['confirmation_token'], new DateTimeImmutable('2026-08-01T10:01:00+00:00'));

        $campaignStore = new CampaignStore($root . '/campaigns', $files);
        $queue = new MailQueue(
            $root . '/queue',
            new class implements EmailTransport {
                public function send(EmailMessage $message, string $idempotencyKey): array
                {
                    return [
                        'provider' => 'test',
                        'provider_id' => $idempotencyKey,
                        'accepted' => true,
                    ];
                }
            },
            $files,
        );

        foreach (['posts', 'drafts', 'authors', 'assets'] as $directory) {
            mkdir($root . '/' . $directory, 0775, true);
        }
        $repository = new Repository(
            $root . '/posts',
            $root . '/drafts',
            $root . '/authors',
            $root . '/assets',
        );
        $markdown = new Markdown();
        $workspace = new MailWorkspace(
            $repository,
            $subscriberStore,
            $markdown,
            'https://example.test',
        );
        $reviewer = new CampaignDraftReviewer($subscriberStore, $markdown);
        $dispatcher = new CampaignDispatcher(
            $workspace,
            $subscriberStore,
            $campaignStore,
            $queue,
            'https://example.test',
        );

        $draft = new CampaignDraft(
            id: str_repeat('a', 32),
            subject: 'A campaign',
            preheader: 'Preview',
            body: "Hello readers.\n",
            version: 3,
            createdAt: new DateTimeImmutable('2026-08-01T09:00:00+00:00'),
            updatedAt: new DateTimeImmutable('2026-08-01T09:30:00+00:00'),
            createdBy: 'owner@example.com',
            sourceType: 'post',
            sourceId: 'hello-readers',
            sourceRevision: 'rev-1',
            sourceHash: hash('sha256', 'source'),
            sourceCreatedAt: new DateTimeImmutable('2026-07-31T00:00:00+00:00'),
        );

        $second = $subscriberStore->request('second@example.com', new DateTimeImmutable('2026-08-01T10:02:00+00:00'));
        $subscriberStore->confirm($second['confirmation_token'], new DateTimeImmutable('2026-08-01T10:03:00+00:00'));

        $campaign = $dispatcher->confirmDraftAndQueue(
            $draft,
            $reviewer,
            new DateTimeImmutable('2026-08-01T10:04:00+00:00'),
        );

        self::assertSame(2, $campaign->recipientCount());
        self::assertSame(
            ['first@example.com', 'second@example.com'],
            array_column($campaign->recipients, 'email'),
        );
        self::assertSame(2, count(glob($root . '/queue/*.json') ?: []));

        $third = $subscriberStore->request('third@example.com', new DateTimeImmutable('2026-08-01T10:05:00+00:00'));
        $subscriberStore->confirm($third['confirmation_token'], new DateTimeImmutable('2026-08-01T10:06:00+00:00'));

        $stored = $campaignStore->find($campaign->id);
        self::assertNotNull($stored);
        self::assertSame(2, $stored->recipientCount());
        self::assertSame(
            ['first@example.com', 'second@example.com'],
            array_column($stored->recipients, 'email'),
        );
    }
}

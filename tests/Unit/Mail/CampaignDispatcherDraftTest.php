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
use Katakata\Mail\CampaignDraftStore;
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

        [$subscriberStore, $campaignStore, $queue, $reviewer, $dispatcher] = $this->dependencies(
            $root,
            new class implements EmailTransport {
                public function send(EmailMessage $message, string $idempotencyKey): array
                {
                    return ['provider' => 'test', 'provider_id' => $idempotencyKey, 'accepted' => true];
                }
            },
        );

        $first = $subscriberStore->request('first@example.com', new DateTimeImmutable('2026-08-01T10:00:00+00:00'));
        $subscriberStore->confirm($first['confirmation_token'], new DateTimeImmutable('2026-08-01T10:01:00+00:00'));
        $draft = $this->draft();

        $second = $subscriberStore->request('second@example.com', new DateTimeImmutable('2026-08-01T10:02:00+00:00'));
        $subscriberStore->confirm($second['confirmation_token'], new DateTimeImmutable('2026-08-01T10:03:00+00:00'));

        $campaign = $dispatcher->confirmDraftAndQueue($draft, $reviewer, new DateTimeImmutable('2026-08-01T10:04:00+00:00'));

        self::assertSame(2, $campaign->recipientCount());
        self::assertSame(['first@example.com', 'second@example.com'], array_column($campaign->recipients, 'email'));
        self::assertSame(2, count(glob($root . '/queue/*.json') ?: []));

        $third = $subscriberStore->request('third@example.com', new DateTimeImmutable('2026-08-01T10:05:00+00:00'));
        $subscriberStore->confirm($third['confirmation_token'], new DateTimeImmutable('2026-08-01T10:06:00+00:00'));

        $stored = $campaignStore->find($campaign->id);
        self::assertNotNull($stored);
        self::assertSame(2, $stored->recipientCount());
        self::assertSame(['first@example.com', 'second@example.com'], array_column($stored->recipients, 'email'));
    }

    public function testClaimedDraftCanResumeAfterQueueFailureWithoutDuplicates(): void
    {
        $root = sys_get_temp_dir() . '/katakata-campaign-resume-' . bin2hex(random_bytes(6));
        mkdir($root, 0775, true);

        $files = new AtomicFile();
        $draftStore = new CampaignDraftStore($root . '/drafts-store', $files);
        $draftStore->create($this->draft());
        $claim = $draftStore->claimConfirmation(str_repeat('a', 32), 3, new DateTimeImmutable('2026-08-01T10:04:00+00:00'));
        self::assertTrue($claim['acquired']);

        $failures = 1;
        [$subscriberStore, $campaignStore, $queue, $reviewer, $dispatcher] = $this->dependencies(
            $root,
            new class($failures) implements EmailTransport {
                public function __construct(private int &$failures)
                {
                }

                public function send(EmailMessage $message, string $idempotencyKey): array
                {
                    if ($this->failures-- > 0) {
                        throw new \RuntimeException('Transient transport failure.');
                    }

                    return ['provider' => 'test', 'provider_id' => $idempotencyKey, 'accepted' => true];
                }
            },
        );

        $first = $subscriberStore->request('first@example.com', new DateTimeImmutable('2026-08-01T10:00:00+00:00'));
        $subscriberStore->confirm($first['confirmation_token'], new DateTimeImmutable('2026-08-01T10:01:00+00:00'));
        $claimed = $claim['draft'];

        $campaign = $dispatcher->confirmDraftAndQueue($claimed, $reviewer, $claimed->confirmedAt);
        self::assertSame($claimed->confirmedCampaignId, $campaign->id);
        self::assertSame(1, count(glob($root . '/campaigns/*.json') ?: []));
        self::assertSame(1, count(glob($root . '/queue/*.json') ?: []));

        $queue->work(10, new DateTimeImmutable('2026-08-01T10:05:00+00:00'));
        $campaignAgain = $dispatcher->confirmDraftAndQueue($claimed, $reviewer, $claimed->confirmedAt);
        $queue->work(10, new DateTimeImmutable('2026-08-01T10:07:00+00:00'));

        self::assertSame($campaign->id, $campaignAgain->id);
        self::assertSame(1, count(glob($root . '/campaigns/*.json') ?: []));
        self::assertSame(1, count(glob($root . '/queue/*.json') ?: []));
    }

    /** @return array{SubscriberStore,CampaignStore,MailQueue,CampaignDraftReviewer,CampaignDispatcher} */
    private function dependencies(string $root, EmailTransport $transport): array
    {
        $files = new AtomicFile();
        $subscriberStore = new SubscriberStore($root . '/subscribers.json', 'test-secret', $files);
        $campaignStore = new CampaignStore($root . '/campaigns', $files);
        $queue = new MailQueue($root . '/queue', $transport, $files);

        foreach (['posts', 'drafts', 'authors', 'assets'] as $directory) {
            if (!is_dir($root . '/' . $directory)) {
                mkdir($root . '/' . $directory, 0775, true);
            }
        }
        $repository = new Repository($root . '/posts', $root . '/drafts', $root . '/authors', $root . '/assets');
        $markdown = new Markdown();
        $workspace = new MailWorkspace($repository, $subscriberStore, $markdown, 'https://example.test');
        $reviewer = new CampaignDraftReviewer($subscriberStore, $markdown);
        $dispatcher = new CampaignDispatcher($workspace, $subscriberStore, $campaignStore, $queue, 'https://example.test');

        return [$subscriberStore, $campaignStore, $queue, $reviewer, $dispatcher];
    }

    private function draft(): CampaignDraft
    {
        return new CampaignDraft(
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
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Mail;

use DateTimeImmutable;
use Katakata\Distribution\EmailMessage;
use Katakata\Distribution\MailQueue;
use Katakata\Distribution\SubscriberStore;
use RuntimeException;

final class CampaignDispatcher
{
    public function __construct(
        private readonly MailWorkspace $workspace,
        private readonly SubscriberStore $subscribers,
        private readonly CampaignStore $campaigns,
        private readonly MailQueue $queue,
        private readonly string $appUrl,
    ) {
    }

    public function confirmAndQueue(string $slug, ?DateTimeImmutable $now = null): Campaign
    {
        $proof = $this->workspace->dispatchProof($slug);
        if ($proof === null) {
            throw new RuntimeException('Newsletter campaign is not eligible for dispatch.');
        }

        return $this->queueCampaign(
            id: bin2hex(random_bytes(16)),
            postSlug: $slug,
            subject: $proof['subject'],
            canonicalUrl: $proof['canonical_url'],
            html: $proof['html'],
            text: $proof['text'],
            recipients: $this->subscribers->deliverable(),
            now: $now,
        );
    }

    public function confirmDraftAndQueue(CampaignDraft $draft, CampaignDraftReviewer $reviewer, ?DateTimeImmutable $now = null): Campaign
    {
        $proof = $reviewer->review($draft);
        if ($proof['recipient_count'] === 0 || $draft->subject === '' || trim($draft->body) === '') {
            throw new RuntimeException('Campaign draft is not eligible for dispatch.');
        }

        $campaignId = $draft->confirmedCampaignId ?? hash('md5', 'campaign-draft:' . $draft->id);
        $existing = $this->campaigns->find($campaignId);
        if ($existing !== null) {
            return $existing;
        }

        $canonicalUrl = $draft->sourceType === 'post' && $draft->sourceId !== null
            ? rtrim($this->appUrl, '/') . '/posts/' . rawurlencode($draft->sourceId)
            : rtrim($this->appUrl, '/') . '/mail/campaign-drafts/' . rawurlencode($draft->id);

        return $this->queueCampaign(
            id: $campaignId,
            postSlug: $draft->sourceId ?? '',
            subject: $draft->subject,
            canonicalUrl: $canonicalUrl,
            html: $proof['html'],
            text: $proof['text'],
            recipients: $proof['recipients'],
            now: $now,
        );
    }

    /** @param list<array{email:string,unsubscribe_token:string}> $recipients */
    private function queueCampaign(
        string $id,
        string $postSlug,
        string $subject,
        string $canonicalUrl,
        string $html,
        string $text,
        array $recipients,
        ?DateTimeImmutable $now,
    ): Campaign {
        $now ??= new DateTimeImmutable();
        $campaign = new Campaign(
            id: $id,
            postSlug: $postSlug,
            subject: $subject,
            canonicalUrl: $canonicalUrl,
            html: $html,
            text: $text,
            recipients: $recipients,
            status: 'queued',
            createdAt: $now,
            confirmedAt: $now,
        );

        $existing = $this->campaigns->find($campaign->id);
        if ($existing !== null) {
            return $existing;
        }
        $this->campaigns->create($campaign);

        foreach ($recipients as $recipient) {
            $unsubscribe = rtrim($this->appUrl, '/') . '/newsletter/unsubscribe?token='
                . rawurlencode($recipient['unsubscribe_token']);
            $messageHtml = $campaign->html . sprintf(
                "\n<p><a href=\"%s\">Unsubscribe</a></p>",
                htmlspecialchars($unsubscribe, ENT_QUOTES, 'UTF-8'),
            );
            $messageText = rtrim($campaign->text) . "\n\nUnsubscribe: {$unsubscribe}\n";

            $this->queue->enqueue(
                'campaign:' . $campaign->id . ':' . hash('sha256', $recipient['email']),
                new EmailMessage($recipient['email'], $campaign->subject, $messageHtml, $messageText),
                $now,
            );
        }

        return $campaign;
    }
}

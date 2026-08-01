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

        $now ??= new DateTimeImmutable();
        $recipients = $this->subscribers->deliverable();
        $campaign = new Campaign(
            id: bin2hex(random_bytes(16)),
            postSlug: $slug,
            subject: $proof['subject'],
            canonicalUrl: $proof['canonical_url'],
            html: $proof['html'],
            text: $proof['text'],
            recipients: $recipients,
            status: 'queued',
            createdAt: $now,
            confirmedAt: $now,
        );

        $this->campaigns->create($campaign);

        foreach ($recipients as $recipient) {
            $unsubscribe = rtrim($this->appUrl, '/') . '/newsletter/unsubscribe?token='
                . rawurlencode($recipient['unsubscribe_token']);
            $html = $campaign->html . sprintf(
                "\n<p><a href=\"%s\">Unsubscribe</a></p>",
                htmlspecialchars($unsubscribe, ENT_QUOTES, 'UTF-8'),
            );
            $text = rtrim($campaign->text) . "\n\nUnsubscribe: {$unsubscribe}\n";

            $this->queue->enqueue(
                'campaign:' . $campaign->id . ':' . hash('sha256', $recipient['email']),
                new EmailMessage($recipient['email'], $campaign->subject, $html, $text),
                $now,
            );
        }

        return $campaign;
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Mail;

use DateTimeImmutable;
use Katakata\Email\Mailbox;

final class MailAttention
{
    public function __construct(
        private readonly Mailbox $mailbox,
        private readonly CampaignStore $campaigns,
        private readonly CampaignStatus $status,
    ) {
    }

    /** @return array{reader:int,campaigns:int,total:int,detail:string} */
    public function summary(): array
    {
        $reader = $this->mailbox->unreadCount();
        $campaigns = 0;

        foreach ($this->campaigns->all() as $campaign) {
            $delivery = $this->status->summarize($campaign);
            if (($delivery['retryable'] ?? 0) > 0 || in_array($campaign->status, ['review', 'awaiting_review'], true)) {
                $campaigns++;
            }
        }

        $parts = [];
        if ($reader > 0) {
            $parts[] = $reader . ' reader ' . ($reader === 1 ? 'message' : 'messages');
        }
        if ($campaigns > 0) {
            $parts[] = $campaigns . ' ' . ($campaigns === 1 ? 'campaign needs' : 'campaigns need') . ' attention';
        }

        return [
            'reader' => $reader,
            'campaigns' => $campaigns,
            'total' => $reader + $campaigns,
            'detail' => $parts === [] ? 'No mail needs attention' : implode(' · ', $parts),
        ];
    }

    public function landing(): string
    {
        $readiness = $this->mailbox->readiness();
        if (($readiness['status'] ?? 'needs_setup') !== 'ready') {
            return 'campaigns';
        }

        $latestMessage = null;
        foreach ($this->mailbox->inbox() as $message) {
            if (!$message->unread) {
                continue;
            }
            if ($latestMessage === null || $message->receivedAt > $latestMessage) {
                $latestMessage = $message->receivedAt;
            }
        }

        $latestCampaign = $this->latestCampaignAttentionAt();
        if ($latestMessage === null && $latestCampaign === null) {
            return 'inbox';
        }
        if ($latestMessage === null) {
            return 'campaigns';
        }
        if ($latestCampaign === null) {
            return 'inbox';
        }

        return $latestMessage >= $latestCampaign ? 'inbox' : 'campaigns';
    }

    private function latestCampaignAttentionAt(): ?DateTimeImmutable
    {
        $latest = null;

        foreach ($this->campaigns->all() as $campaign) {
            $delivery = $this->status->summarize($campaign);
            $needsAttention = ($delivery['retryable'] ?? 0) > 0
                || in_array($campaign->status, ['review', 'awaiting_review'], true);
            if (!$needsAttention) {
                continue;
            }

            $at = $campaign->confirmedAt;
            if ($latest === null || $at > $latest) {
                $latest = $at;
            }
        }

        return $latest;
    }
}

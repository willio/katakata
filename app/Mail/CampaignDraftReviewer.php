<?php

declare(strict_types=1);

namespace Katakata\Mail;

use Katakata\Distribution\SubscriberStore;
use Katakata\Rendering\Markdown;

final class CampaignDraftReviewer
{
    public function __construct(
        private readonly SubscriberStore $subscribers,
        private readonly Markdown $markdown,
    ) {
    }

    /** @return array{draft:CampaignDraft,recipient_count:int,recipients:list<array{email:string,unsubscribe_token:string}>,html:string,text:string,warnings:list<string>} */
    public function review(CampaignDraft $draft): array
    {
        $recipients = $this->subscribers->deliverable();
        $html = $this->markdown->render($draft->body);
        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) . "\n";

        $warnings = [];
        if ($draft->subject === '') {
            $warnings[] = 'Subject is missing.';
        }
        if ($draft->preheader === '') {
            $warnings[] = 'Preheader is missing.';
        }
        if (trim($draft->body) === '') {
            $warnings[] = 'Campaign body is empty.';
        }
        if ($recipients === []) {
            $warnings[] = 'No confirmed recipients are eligible.';
        }

        return [
            'draft' => $draft,
            'recipient_count' => count($recipients),
            'recipients' => $recipients,
            'html' => $html,
            'text' => $text,
            'warnings' => $warnings,
        ];
    }
}

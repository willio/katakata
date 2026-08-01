<?php

declare(strict_types=1);

namespace Katakata\Mail;

use RuntimeException;

final class CampaignDraftConflict extends RuntimeException
{
    public function __construct(public readonly CampaignDraft $current)
    {
        parent::__construct('Campaign draft has changed since it was loaded.');
    }
}

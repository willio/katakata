<?php

declare(strict_types=1);

namespace Katakata\Email;

interface ArchivedMailboxProvider extends MailboxProvider
{
    /** @return list<MessageSummary> */
    public function archived(int $limit = 50): array;
}

<?php

declare(strict_types=1);

namespace Katakata\Email;

use DateTimeImmutable;

final class DraftComposer
{
    public function __construct(private readonly DraftStore $drafts)
    {
    }

    public function compose(
        string $to,
        string $subject,
        string $text,
        ?string $inReplyTo = null,
        ?DateTimeImmutable $now = null,
    ): Draft {
        $now ??= new DateTimeImmutable();
        $draft = new Draft(
            id: bin2hex(random_bytes(16)),
            to: trim($to),
            subject: trim($subject),
            text: $text,
            inReplyTo: $inReplyTo,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );

        return $this->drafts->create($draft);
    }
}

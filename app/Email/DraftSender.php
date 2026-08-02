<?php

declare(strict_types=1);

namespace Katakata\Email;

use RuntimeException;

final class DraftSender
{
    public function __construct(
        private readonly DraftStore $drafts,
        private readonly OutboundMailProvider $outbound,
        private readonly ?SentMessageStore $sent = null,
    ) {
    }

    public function send(string $id): void
    {
        $draft = $this->drafts->find($id);
        if ($draft === null) {
            throw new RuntimeException('Mail draft not found.');
        }

        $this->outbound->send($draft);
        $this->sent?->record($draft);
        $this->drafts->delete($id);
    }
}

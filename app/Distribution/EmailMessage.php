<?php

declare(strict_types=1);

namespace Katakata\Distribution;

final class EmailMessage
{
    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $html,
        public readonly string $text,
    ) {
    }

    /** @return array{to: string, subject: string, html: string, text: string} */
    public function toArray(): array
    {
        return ['to' => $this->to, 'subject' => $this->subject, 'html' => $this->html, 'text' => $this->text];
    }
}

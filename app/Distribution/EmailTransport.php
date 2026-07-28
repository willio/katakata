<?php

declare(strict_types=1);

namespace Katakata\Distribution;

interface EmailTransport
{
    /** @return array<string, mixed> */
    public function send(EmailMessage $message, string $idempotencyKey): array;
}

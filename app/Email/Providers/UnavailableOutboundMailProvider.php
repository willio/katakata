<?php

declare(strict_types=1);

namespace Katakata\Email\Providers;

use Katakata\Email\Draft;
use Katakata\Email\OutboundMailProvider;
use RuntimeException;

final class UnavailableOutboundMailProvider implements OutboundMailProvider
{
    public function send(Draft $draft): void
    {
        throw new RuntimeException('Correspondence sending is not configured.');
    }
}

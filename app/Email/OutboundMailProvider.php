<?php

declare(strict_types=1);

namespace Katakata\Email;

interface OutboundMailProvider
{
    public function send(Draft $draft): void;
}

<?php

declare(strict_types=1);

namespace Katakata\Email;

interface ImapWireConnection
{
    /** @return list<string> */
    public function command(string $command): array;

    public function close(): void;
}

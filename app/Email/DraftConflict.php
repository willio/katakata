<?php

declare(strict_types=1);

namespace Katakata\Email;

use RuntimeException;

final class DraftConflict extends RuntimeException
{
    public function __construct(public readonly Draft $current)
    {
        parent::__construct('Correspondence draft changed elsewhere.');
    }
}

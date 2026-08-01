<?php

declare(strict_types=1);

namespace Katakata\Email;

final readonly class Attachment
{
    public function __construct(
        public string $id,
        public string $name,
        public string $mediaType,
        public int $bytes,
    ) {
    }
}

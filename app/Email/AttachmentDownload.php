<?php

declare(strict_types=1);

namespace Katakata\Email;

final readonly class AttachmentDownload
{
    public function __construct(
        public string $name,
        public string $mediaType,
        public string $content,
    ) {
    }
}

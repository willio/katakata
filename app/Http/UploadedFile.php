<?php

declare(strict_types=1);

namespace Katakata\Http;

final readonly class UploadedFile
{
    public function __construct(
        public string $name,
        public string $temporaryPath,
        public int $size,
        public int $error,
        public string $mediaType = '',
    ) {
    }

    public function valid(): bool
    {
        return $this->error === UPLOAD_ERR_OK
            && $this->size > 0
            && $this->temporaryPath !== '';
    }

    public function contents(): ?string
    {
        if (!$this->valid()) {
            return null;
        }
        $contents = file_get_contents($this->temporaryPath);
        return is_string($contents) ? $contents : null;
    }
}

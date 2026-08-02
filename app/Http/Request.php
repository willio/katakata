<?php

declare(strict_types=1);

namespace Katakata\Http;

/**
 * A thin, immutable wrapper around an incoming HTTP request.
 */
final class Request
{
    /**
     * @param array<string, string> $query
     * @param array<string, string> $server
     * @param array<string, string> $body
     * @param array<string, UploadedFile> $files
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $server = [],
        public readonly array $body = [],
        public readonly string $rawBody = '',
        public readonly array $files = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $normalized = rtrim($path, '/');

        return new self(
            method: strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            path: $normalized === '' ? '/' : $normalized,
            query: $_GET,
            server: $_SERVER,
            body: array_filter($_POST, 'is_string'),
            rawBody: (string) file_get_contents('php://input'),
            files: self::normalizeFiles($_FILES),
        );
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $this->server[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function file(string $name): ?UploadedFile
    {
        $file = $this->files[$name] ?? null;
        return $file instanceof UploadedFile ? $file : null;
    }

    /** @param array<string,mixed> $files @return array<string,UploadedFile> */
    private static function normalizeFiles(array $files): array
    {
        $normalized = [];
        foreach ($files as $name => $file) {
            if (!is_array($file) || is_array($file['name'] ?? null)) {
                continue;
            }
            $normalized[(string) $name] = new UploadedFile(
                name: (string) ($file['name'] ?? ''),
                temporaryPath: (string) ($file['tmp_name'] ?? ''),
                size: (int) ($file['size'] ?? 0),
                error: (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE),
                mediaType: (string) ($file['type'] ?? ''),
            );
        }
        return $normalized;
    }
}

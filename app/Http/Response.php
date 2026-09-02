<?php

declare(strict_types=1);

namespace Katakata\Http;

/**
 * A thin HTTP response value object.
 */
final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $body = '',
        public readonly int $status = 200,
        public readonly array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * @param array<mixed> $data
     */
    public static function json(array $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}',
            $status,
            ['Content-Type' => 'application/json'],
        );
    }

    public static function redirect(string $location, int $status = 303): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public static function notFound(?string $body = null): self
    {
        return self::html($body ?? self::renderNotFoundView(), 404);
    }

    public function withoutBody(): self
    {
        return new self('', $this->status, $this->headers);
    }

    private static function renderNotFoundView(): string
    {
        $path = dirname(__DIR__, 2) . '/resources/views/not-found.php';

        if (!is_file($path)) {
            return 'Not Found';
        }

        ob_start();

        require $path;

        return (string) ob_get_clean();
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $this->body;
    }
}

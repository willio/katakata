<?php

declare(strict_types=1);

namespace Katakata\Email;

use RuntimeException;

final class TlsImapWireConnection implements ImapWireConnection
{
    /** @var resource */
    private $stream;
    private int $tag = 0;

    public function __construct(ImapSettings $settings, float $timeout = 10.0)
    {
        if (!$settings->configured() || $settings->encryption !== 'ssl') {
            throw new RuntimeException('The IMAP TLS connection is not configured.');
        }
        $this->assertSafe($settings->host, 'host');

        $context = stream_context_create(['ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $settings->host,
            'SNI_enabled' => true,
        ]]);
        $stream = @stream_socket_client(
            sprintf('tls://%s:%d', $settings->host, $settings->port),
            $errorCode,
            $errorMessage,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context,
        );
        if (!is_resource($stream)) {
            throw new RuntimeException('Unable to connect to the configured IMAP mailbox.');
        }
        stream_set_timeout($stream, max(1, (int) ceil($timeout)));
        $this->stream = $stream;

        $greeting = fgets($this->stream);
        if (!is_string($greeting) || !str_starts_with($greeting, '* OK')) {
            $this->close();
            throw new RuntimeException('The IMAP server returned an invalid greeting.');
        }
    }

    public function command(string $command): array
    {
        $this->assertSafe($command, 'command');
        $tag = sprintf('A%04d', ++$this->tag);
        if (@fwrite($this->stream, $tag . ' ' . $command . "\r\n") === false) {
            throw new RuntimeException('Unable to write to the IMAP connection.');
        }

        $response = [];
        while (!feof($this->stream)) {
            $line = fgets($this->stream);
            if (!is_string($line)) {
                throw new RuntimeException('The IMAP connection ended unexpectedly.');
            }
            $response[] = $line;

            if (preg_match('/\{(\d+)\}\r\n$/', $line, $match) === 1) {
                $literal = $this->readBytes((int) $match[1]);
                $response[] = $literal;
            }

            if (str_starts_with($line, $tag . ' ')) {
                if (!str_starts_with($line, $tag . ' OK')) {
                    throw new RuntimeException('The IMAP server rejected the requested operation.');
                }
                return $response;
            }
        }

        throw new RuntimeException('The IMAP connection ended unexpectedly.');
    }

    public function close(): void
    {
        if (isset($this->stream) && is_resource($this->stream)) {
            @fclose($this->stream);
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    private function readBytes(int $bytes): string
    {
        $value = '';
        while (strlen($value) < $bytes && !feof($this->stream)) {
            $chunk = fread($this->stream, $bytes - strlen($value));
            if (!is_string($chunk) || $chunk === '') {
                throw new RuntimeException('The IMAP literal payload was incomplete.');
            }
            $value .= $chunk;
        }
        return $value;
    }

    private function assertSafe(string $value, string $field): void
    {
        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new RuntimeException('The IMAP ' . $field . ' contains invalid control characters.');
        }
    }
}

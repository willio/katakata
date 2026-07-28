<?php

declare(strict_types=1);

namespace Katakata\Distribution;

final class ConfirmationMailer
{
    public function __construct(
        private readonly MailQueue $queue,
        private readonly string $appUrl,
        private readonly string $siteName,
    ) {
    }

    /** @param array{email: string, confirmation_token: string, expires_at: string} $request */
    public function queue(array $request): string
    {
        $url = rtrim($this->appUrl, '/') . '/newsletter/confirm?token=' . rawurlencode($request['confirmation_token']);
        $subject = 'Confirm your ' . $this->siteName . ' subscription';
        $text = "Confirm your subscription:\n\n{$url}\n\nThis link expires in 48 hours.\n";
        $html = '<p>Confirm your subscription:</p><p><a href="'
            . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
            . '">Confirm subscription</a></p><p>This link expires in 48 hours.</p>';

        return $this->queue->enqueue(
            'newsletter-confirmation:' . hash('sha256', $request['email'] . "\n" . $request['confirmation_token']),
            new EmailMessage($request['email'], $subject, $html, $text),
        );
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Distribution;

use Katakata\Content\Post;
use RuntimeException;

final class NewsletterDispatcher
{
    public function __construct(
        private readonly NewsletterAdapter $adapter,
        private readonly SubscriberStore $subscribers,
        private readonly MailQueue $queue,
        private readonly string $appUrl,
    ) {
    }

    /** @return array{queued: int, payload_path: string} */
    public function dispatch(Post $post): array
    {
        $result = $this->adapter->distribute($post);
        $payload = json_decode((string) file_get_contents($result['path']), true);
        if (!is_array($payload)) {
            throw new RuntimeException('Newsletter payload is invalid.');
        }

        $queued = 0;
        foreach ($this->subscribers->deliverable() as $subscriber) {
            $unsubscribe = rtrim($this->appUrl, '/') . '/newsletter/unsubscribe?token='
                . rawurlencode($subscriber['unsubscribe_token']);
            $html = (string) ($payload['html'] ?? '') . sprintf(
                "\n<p><a href=\"%s\">Unsubscribe</a></p>",
                htmlspecialchars($unsubscribe, ENT_QUOTES, 'UTF-8'),
            );
            $text = rtrim((string) ($payload['text'] ?? ''))
                . "\n\nUnsubscribe: {$unsubscribe}\n";
            $this->queue->enqueue(
                'newsletter:' . $post->slug . ':' . hash('sha256', $subscriber['email']),
                new EmailMessage(
                    $subscriber['email'],
                    (string) ($payload['subject'] ?? $post->title),
                    $html,
                    $text,
                ),
            );
            $queued++;
        }

        return ['queued' => $queued, 'payload_path' => $result['path']];
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use Katakata\View;
use PHPUnit\Framework\TestCase;

final class NewsletterViewTest extends TestCase
{
    public function testSubscribeFormUsesAccessibleCanonicalFieldsAndButtons(): void
    {
        $html = (new View(dirname(__DIR__, 2) . '/resources/views'))->render('newsletter', [
            'mode' => 'subscribe',
            'message' => null,
            'error' => null,
            'token' => null,
            'csrf' => 'csrf-token',
            'siteName' => 'Katakata',
        ]);

        self::assertStringContainsString('action="/newsletter/subscribe"', $html);
        self::assertStringContainsString('for="newsletter-email"', $html);
        self::assertStringContainsString('placeholder="Email"', $html);
        self::assertStringContainsString('class="field-clear"', $html);
        self::assertStringContainsString('class="button"', $html);
    }

    public function testUnsubscribeRequiresAConfirmationPost(): void
    {
        $html = (new View(dirname(__DIR__, 2) . '/resources/views'))->render('newsletter', [
            'mode' => 'unsubscribe',
            'message' => null,
            'error' => null,
            'token' => 'signed-token',
            'csrf' => 'csrf-token',
            'siteName' => 'Katakata',
        ]);

        self::assertStringContainsString('method="post" action="/newsletter/unsubscribe"', $html);
        self::assertStringContainsString('value="signed-token"', $html);
        self::assertStringContainsString('value="csrf-token"', $html);
    }
}

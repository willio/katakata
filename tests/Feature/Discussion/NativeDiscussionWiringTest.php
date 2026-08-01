<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature\Discussion;

use Katakata\Discussion\NativeDiscussionService;
use PHPUnit\Framework\TestCase;

final class NativeDiscussionWiringTest extends TestCase
{
    public function testBootstrapResolvesTheNativeDiscussionServiceUsedByTheArticleRoute(): void
    {
        $app = require dirname(__DIR__, 3) . '/bootstrap/app.php';

        self::assertInstanceOf(NativeDiscussionService::class, $app->make(NativeDiscussionService::class));
    }

    public function testArticleRouteSuppliesTheDiscussionViewContract(): void
    {
        $route = file_get_contents(dirname(__DIR__, 3) . '/routes/article.php');

        self::assertIsString($route);
        self::assertStringContainsString("'discussion' =>", $route);
        self::assertStringContainsString("'commentState' =>", $route);
        self::assertStringContainsString("'csrf' =>", $route);
    }

    public function testPublicSubmissionPassesTheHoneypotToTheNativeStoreBoundary(): void
    {
        $route = file_get_contents(dirname(__DIR__, 3) . '/routes/article.php');

        self::assertIsString($route);
        self::assertStringContainsString("spam: ['honeypot' => \$request->body['honeypot'] ?? null]", $route);
    }
}

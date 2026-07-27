<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use Katakata\Application;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ApplicationTest extends TestCase
{
    public function test_it_boots_and_loads_configuration(): void
    {
        $app = new Application(dirname(__DIR__, 2));
        $app->boot();

        $this->assertTrue($app->isBooted());
        $this->assertSame('Katakata', $app->config()->get('app.name'));
    }

    public function test_configuration_is_immutable_after_boot(): void
    {
        $app = new Application(dirname(__DIR__, 2));
        $app->boot();

        $this->expectException(RuntimeException::class);
        $app->config()->set('app', []);
    }

    public function test_content_paths_resolve_beneath_the_base_path(): void
    {
        $app = new Application(dirname(__DIR__, 2));

        $this->assertSame(
            dirname(__DIR__, 2) . '/content/posts',
            $app->contentPath('posts'),
        );
    }
}

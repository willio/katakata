<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use Katakata\Http\Request;
use Katakata\Http\Response;
use Katakata\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function test_it_dispatches_a_matching_route(): void
    {
        $router = new Router();
        $router->get('/', fn (Request $request): Response => Response::html('home'));

        $response = $router->dispatch(new Request('GET', '/'));

        $this->assertSame(200, $response->status);
        $this->assertSame('home', $response->body);
    }

    public function test_it_returns_404_for_an_unmatched_route(): void
    {
        $router = new Router();

        $response = $router->dispatch(new Request('GET', '/nowhere'));

        $this->assertSame(404, $response->status);
    }
}

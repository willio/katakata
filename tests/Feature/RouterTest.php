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

    public function test_unmatched_route_renders_the_styled_not_found_view(): void
    {
        $router = new Router();

        $response = $router->dispatch(new Request('GET', '/nowhere'));

        $this->assertSame(404, $response->status);
        $this->assertSame('text/html; charset=utf-8', $response->headers['Content-Type']);
        $this->assertStringContainsString('<!doctype html>', $response->body);
        $this->assertStringContainsString('Katakata', $response->body);
        $this->assertStringContainsString('href="/"', $response->body);
    }

    public function test_head_requests_run_the_get_handler_and_suppress_the_body(): void
    {
        $router = new Router();
        $handled = false;
        $router->get('/', function (Request $request) use (&$handled): Response {
            $handled = true;

            return new Response('home', 200, ['Content-Type' => 'text/html; charset=utf-8', 'X-Test' => 'yes']);
        });

        $get = $router->dispatch(new Request('GET', '/'));
        $head = $router->dispatch(new Request('HEAD', '/'));

        $this->assertTrue($handled);
        $this->assertSame(200, $head->status);
        $this->assertSame($get->headers, $head->headers);
        $this->assertSame('yes', $head->headers['X-Test']);
        $this->assertSame('', $head->body);
        $this->assertSame('home', $get->body);
    }

    public function test_head_requests_suppress_the_not_found_body(): void
    {
        $router = new Router();

        $response = $router->dispatch(new Request('HEAD', '/nowhere'));

        $this->assertSame(404, $response->status);
        $this->assertSame('text/html; charset=utf-8', $response->headers['Content-Type']);
        $this->assertSame('', $response->body);
    }
}

<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use Katakata\Http\Request;
use Katakata\Http\Response;
use Katakata\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterParametersTest extends TestCase
{
    public function testItDispatchesParameterizedRoutesInDeclaredOrder(): void
    {
        $router = new Router();
        $router->get('/healthz', static fn (Request $request): Response => Response::html('health'));
        $router->get('/{year}/{month}/{slug}', static function (
            Request $request,
            string $year,
            string $month,
            string $slug,
        ): Response {
            return Response::html("{$year}|{$month}|{$slug}");
        });

        self::assertSame(
            '2026|07|hello-world',
            $router->dispatch(new Request('GET', '/2026/07/hello-world'))->body,
        );
        self::assertSame(
            'health',
            $router->dispatch(new Request('GET', '/healthz'))->body,
        );
    }

    public function testUnmatchedRoutesReturnNotFound(): void
    {
        self::assertSame(
            404,
            (new Router())->dispatch(new Request('GET', '/missing'))->status,
        );
    }
}

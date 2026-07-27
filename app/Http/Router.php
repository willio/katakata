<?php

declare(strict_types=1);

namespace Katakata\Http;

use Closure;

/**
 * A minimal route table and dispatcher.
 *
 * Routes are registered in routes/web.php and matched against the
 * incoming request's method and path. There are no route groups,
 * middleware stacks, or route caching yet — those arrive with later
 * phases, and only if the writing and reading experience actually
 * needs them. Calm software: every feature must justify itself.
 */
final class Router
{
    /** @var array<string, array<string, Closure>> */
    private array $routes = [];

    public function get(string $path, Closure $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, Closure $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    private function addRoute(string $method, string $path, Closure $handler): void
    {
        $normalized = $path === '/' ? '/' : rtrim($path, '/');
        $this->routes[$method][$normalized] = $handler;
    }

    /**
     * @return array<int, array{method: string, path: string}>
     */
    public function all(): array
    {
        $list = [];

        foreach ($this->routes as $method => $paths) {
            foreach (array_keys($paths) as $path) {
                $list[] = ['method' => $method, 'path' => $path];
            }
        }

        return $list;
    }

    public function dispatch(Request $request): Response
    {
        $handler = $this->routes[$request->method][$request->path] ?? null;

        if ($handler === null) {
            return Response::notFound();
        }

        return $handler($request);
    }
}

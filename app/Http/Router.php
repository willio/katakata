<?php

declare(strict_types=1);

namespace Katakata\Http;

use Closure;

final class Router
{
    /** @var array<string, array<int, array{path: string, pattern: string, parameters: array<int, string>, handler: Closure}>> */
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
        $parameters = [];
        $quoted = preg_quote($normalized, '#');
        $pattern = preg_replace_callback(
            '/\\\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\\\}/',
            static function (array $match) use (&$parameters): string {
                $parameters[] = $match[1];

                return '([^/]+)';
            },
            $quoted,
        );

        $this->routes[$method][] = [
            'path' => $normalized,
            'pattern' => '#^' . $pattern . '$#',
            'parameters' => $parameters,
            'handler' => $handler,
        ];
    }

    /** @return array<int, array{method: string, path: string}> */
    public function all(): array
    {
        $list = [];

        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $route) {
                $list[] = ['method' => $method, 'path' => $route['path']];
            }
        }

        return $list;
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes[$request->method] ?? [] as $route) {
            if (!preg_match($route['pattern'], $request->path, $matches)) {
                continue;
            }

            array_shift($matches);
            $parameters = array_map('rawurldecode', $matches);

            return ($route['handler'])($request, ...$parameters);
        }

        return Response::notFound();
    }
}

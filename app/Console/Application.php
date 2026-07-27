<?php

declare(strict_types=1);

namespace Katakata\Console;

use Katakata\Application as Kernel;
use Katakata\Http\Router;

/**
 * A deliberately small CLI dispatcher.
 *
 * Commands are plain closures for now. As Phase 1 introduces real
 * editorial and content commands (drafts, publishing, indexing),
 * this can grow into dedicated command classes without changing how
 * bin/katakata invokes it.
 */
final class Application
{
    /** @var array<string, callable(array<int, string>): int> */
    private array $commands = [];

    public function __construct(private readonly Kernel $app)
    {
        $this->commands['about'] = fn (): int => $this->about();
        $this->commands['routes:list'] = fn (): int => $this->routesList();
        $this->commands['serve'] = fn (array $args): int => $this->serve($args);
    }

    /**
     * @param array<int, string> $argv
     */
    public function run(array $argv): int
    {
        $name = $argv[0] ?? 'about';
        $arguments = array_slice($argv, 1);

        if (!isset($this->commands[$name])) {
            fwrite(STDERR, "Unknown command [{$name}].\n");
            fwrite(STDOUT, 'Available commands: ' . implode(', ', array_keys($this->commands)) . "\n");
            return 1;
        }

        return ($this->commands[$name])($arguments);
    }

    private function about(): int
    {
        $name = $this->app->config()->get('app.name', 'Katakata');
        $tagline = $this->app->config()->get('app.tagline', '');

        fwrite(STDOUT, "{$name}\n{$tagline}\n");
        return 0;
    }

    private function routesList(): int
    {
        $router = new Router();
        $app = $this->app;
        require $this->app->routesPath('web.php');

        foreach ($router->all() as $route) {
            fwrite(STDOUT, sprintf("%-6s %s\n", $route['method'], $route['path']));
        }

        return 0;
    }

    /**
     * @param array<int, string> $args
     */
    private function serve(array $args): int
    {
        $host = $args[0] ?? '127.0.0.1:8000';
        $public = $this->app->basePath('public');

        fwrite(STDOUT, "Serving Katakata at http://{$host}\n");
        passthru(sprintf('php -S %s -t %s', escapeshellarg($host), escapeshellarg($public)));

        return 0;
    }
}

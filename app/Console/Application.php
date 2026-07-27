<?php

declare(strict_types=1);

namespace Katakata\Console;

use Katakata\Application as Kernel;
use Katakata\Content\Repository;
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
        $this->commands['content:list'] = fn (): int => $this->contentList();
        $this->commands['content:validate'] = fn (): int => $this->contentValidate();
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

    private function contentList(): int
    {
        $repository = $this->app->make(Repository::class);

        fwrite(STDOUT, 'Posts (' . count($repository->posts()) . "):\n");
        foreach ($repository->posts() as $post) {
            fwrite(STDOUT, sprintf("  %s  %-20s  %s\n", $post->date->format('Y-m-d'), $post->slug, $post->title));
        }

        fwrite(STDOUT, "\nDrafts (" . count($repository->drafts()) . "):\n");
        foreach ($repository->drafts() as $draft) {
            fwrite(STDOUT, sprintf("  %-20s  %s\n", $draft->slug, $draft->title));
        }

        fwrite(STDOUT, "\nAuthors (" . count($repository->authors()) . "):\n");
        foreach ($repository->authors() as $author) {
            fwrite(STDOUT, sprintf("  %-20s  %s\n", $author->slug, $author->name));
        }

        fwrite(STDOUT, "\nAssets (" . count($repository->assets()) . "):\n");
        foreach ($repository->assets() as $asset) {
            fwrite(STDOUT, sprintf("  %-20s  %s, %d bytes\n", $asset->filename, $asset->mimeType, $asset->bytes));
        }

        if ($repository->errors() !== []) {
            fwrite(STDOUT, "\nSkipped due to validation errors:\n");
            foreach ($repository->errors() as $error) {
                fwrite(STDOUT, "  {$error}\n");
            }
        }

        return 0;
    }

    private function contentValidate(): int
    {
        $repository = $this->app->make(Repository::class);
        $repository->refresh();

        // Force a full build of every content type so every error surfaces.
        $repository->posts();
        $repository->drafts();
        $repository->authors();
        $repository->assets();

        $errors = $repository->errors();

        if ($errors === []) {
            fwrite(STDOUT, "Content is valid.\n");
            return 0;
        }

        fwrite(STDERR, "Content validation errors:\n");
        foreach ($errors as $error) {
            fwrite(STDERR, "  {$error}\n");
        }

        return 1;
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

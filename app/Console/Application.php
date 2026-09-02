<?php

declare(strict_types=1);

namespace Katakata\Console;

use Katakata\Application as Kernel;
use Katakata\Analytics\AnalyticsStore;
use Katakata\Auth\AccountStore;
use DateTimeImmutable;
use Katakata\Content\Repository;
use Katakata\Editorial\DraftEditor;
use Katakata\Editorial\ContentTrash;
use Katakata\Editorial\Editor;
use Katakata\Editorial\Publisher;
use Katakata\Editorial\RevisionStore;
use Katakata\Editorial\Scheduler;
use Katakata\Distribution\Distributor;
use Katakata\Distribution\MailQueue;
use Katakata\Distribution\NewsletterDispatcher;
use Katakata\Distribution\ThreadsEngagementSync;
use Katakata\Distribution\ThreadsReplySync;
use Katakata\Discussion\DiscussionManager;
use Katakata\Seo\SeoChecker;

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
    use ImportCommands;

    /** @var array<string, callable(array<int, string>): int> */
    private array $commands = [];

    public function __construct(private readonly Kernel $app)
    {
        $this->commands['about'] = fn (): int => $this->about();
        $this->commands['auth:owner'] = fn (array $args): int => $this->authOwner($args);
        $this->commands['auth:invite'] = fn (array $args): int => $this->authInvite($args);
        $this->commands['routes:list'] = fn (): int => $this->routesList();
        $this->commands['serve'] = fn (array $args): int => $this->serve($args);
        $this->commands['content:list'] = fn (): int => $this->contentList();
        $this->commands['content:validate'] = fn (): int => $this->contentValidate();
        $this->commands['import:document'] = fn (array $args): int => $this->importDocument($args);
        $this->commands['import:directory'] = fn (array $args): int => $this->importDirectory($args);
        $this->commands['draft:create'] = fn (array $args): int => $this->draftCreate($args);
        $this->commands['draft:edit'] = fn (array $args): int => $this->draftEdit($args);
        $this->commands['draft:schedule'] = fn (array $args): int => $this->draftSchedule($args);
        $this->commands['draft:publish'] = fn (array $args): int => $this->draftPublish($args);
        $this->commands['publish:due'] = fn (): int => $this->publishDue();
        $this->commands['revisions:list'] = fn (array $args): int => $this->revisionsList($args);
        $this->commands['trash:purge'] = fn (array $args): int => $this->trashPurge($args);
        $this->commands['distribution:publish'] = fn (array $args): int => $this->distributionPublish($args);
        $this->commands['mail:work'] = fn (array $args): int => $this->mailWork($args);
        $this->commands['resend:webhooks:check'] = fn (): int => $this->resendWebhooksCheck();
        $this->commands['newsletter:dispatch'] = fn (array $args): int => $this->newsletterDispatch($args);
        $this->commands['threads:sync'] = fn (): int => $this->threadsSync();
        $this->commands['threads:engagement:sync'] = fn (): int => $this->threadsEngagementSync();
        $this->commands['analytics:check'] = fn (): int => $this->analyticsCheck();
        $this->commands['analytics:prune'] = fn (): int => $this->analyticsPrune();
        $this->commands['seo:check'] = fn (): int => $this->seoCheck();
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

    /** @param array<int, string> $args */
    private function authOwner(array $args): int
    {
        [$email, $password] = [$args[0] ?? '', $args[1] ?? ''];
        if ($email === '' || $password === '') {
            return $this->usage('auth:owner <email> <password>');
        }

        try {
            $account = $this->app->make(AccountStore::class)->createOwner($email, $password);
            fwrite(STDOUT, "Owner created for {$account['email']}.\n");
            return 0;
        } catch (\Throwable $error) {
            fwrite(STDERR, $error->getMessage() . "\n");
            return 1;
        }
    }

    /** @param array<int, string> $args */
    private function authInvite(array $args): int
    {
        [$email, $role] = [$args[0] ?? '', $args[1] ?? 'editor'];
        if ($email === '') {
            return $this->usage('auth:invite <email> [admin|editor]');
        }

        try {
            $invite = $this->app->make(AccountStore::class)->invite($email, $role);
            $url = rtrim((string) $this->app->config()->get('app.url', 'http://localhost:8000'), '/');
            fwrite(STDOUT, "{$url}/register?token={$invite['token']}\nExpires {$invite['expires_at']}\n");
            return 0;
        } catch (\Throwable $error) {
            fwrite(STDERR, $error->getMessage() . "\n");
            return 1;
        }
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
        $app = $this->app;
        $router = require $this->app->basePath('bootstrap/routes.php');

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


    /** @param array<int, string> $args */
    private function draftCreate(array $args): int
    {
        $slug = $args[0] ?? '';
        $title = $args[1] ?? '';
        if ($slug === '' || $title === '') {
            return $this->usage('draft:create <slug> <title>');
        }

        try {
            $path = $this->app->make(DraftEditor::class)->create($slug, $title);
        } catch (\InvalidArgumentException $e) {
            fwrite(STDERR, $e->getMessage() . "\n");
            return 1;
        }

        fwrite(STDOUT, "Created {$path}\n");
        return 0;
    }

    /** @param array<int, string> $args */
    private function draftEdit(array $args): int
    {
        $draft = $this->draft($args[0] ?? '');
        if ($draft === null) {
            return 1;
        }

        $editor = getenv('EDITOR') ?: '';
        if ($editor === '') {
            fwrite(STDERR, "EDITOR is not configured.\n");
            return 1;
        }

        $this->app->make(Editor::class)->edit($draft->slug, $draft->path, $editor);
        fwrite(STDOUT, "Saved {$draft->path}\n");
        return 0;
    }

    /** @param array<int, string> $args */
    private function draftSchedule(array $args): int
    {
        $draft = $this->draft($args[0] ?? '');
        if ($draft === null || !isset($args[1])) {
            return $draft === null ? 1 : $this->usage('draft:schedule <slug> <ISO-8601>');
        }

        try {
            $at = new DateTimeImmutable($args[1]);
            $this->app->make(DraftEditor::class)->schedule($draft, $at);
        } catch (\Exception) {
            fwrite(STDERR, "Invalid schedule [{$args[1]}].\n");
            return 1;
        }

        fwrite(STDOUT, "Scheduled {$draft->slug} for {$at->format(DateTimeImmutable::ATOM)}\n");
        return 0;
    }

    /** @param array<int, string> $args */
    private function draftPublish(array $args): int
    {
        $draft = $this->draft($args[0] ?? '');
        if ($draft === null) {
            return 1;
        }

        try {
            $at = isset($args[1]) ? new DateTimeImmutable($args[1]) : null;
            $path = $this->app->make(Publisher::class)->publish($draft, $at);
        } catch (\Exception $e) {
            fwrite(STDERR, $e->getMessage() . "\n");
            return 1;
        }

        fwrite(STDOUT, "Published {$path}\n");
        return 0;
    }

    private function publishDue(): int
    {
        $repository = $this->app->make(Repository::class);
        $due = $this->app->make(Scheduler::class)->due($repository->drafts());
        foreach ($due as $draft) {
            $at = new DateTimeImmutable((string) $draft->meta['publish_at']);
            $path = $this->app->make(Publisher::class)->publish($draft, $at);
            fwrite(STDOUT, "Published {$path}\n");
        }

        fwrite(STDOUT, count($due) . " scheduled draft(s) published.\n");
        return 0;
    }

    /** @param array<int, string> $args */
    private function trashPurge(array $args): int
    {
        [$type, $id] = [$args[0] ?? '', $args[1] ?? ''];
        $confirmation = '';
        foreach ($args as $argument) {
            if (str_starts_with($argument, '--confirm=')) {
                $confirmation = substr($argument, strlen('--confirm='));
            }
        }
        if ($type === '' || $id === '' || $confirmation === '') {
            return $this->usage('trash:purge <draft|post> <trash-id> --confirm=<trash-id>');
        }
        try {
            $this->app->make(ContentTrash::class)->purge($type, $id, $confirmation);
            fwrite(STDOUT, "Purged {$type} Trash item {$id}.\n");
            return 0;
        } catch (\Throwable $error) {
            fwrite(STDERR, $error->getMessage() . "\n");
            return 1;
        }
    }

    /** @param array<int, string> $args */

    /** @param array<int, string> $args */
    private function distributionPublish(array $args): int
    {
        $slug = $args[0] ?? '';
        $channel = $args[1] ?? null;
        if ($slug === '') {
            return $this->usage('distribution:publish <post-slug> [newsletter]');
        }

        $post = $this->app->make(Repository::class)->findPost($slug);
        if ($post === null || !$post->isPublished()) {
            fwrite(STDERR, "Published post [{$slug}] not found.\n");
            return 1;
        }

        $deliveries = $this->app->make(Distributor::class)->distribute($post, $channel);
        if ($deliveries === []) {
            fwrite(STDERR, "Distribution channel [{$channel}] not found.\n");
            return 1;
        }

        $failed = false;
        foreach ($deliveries as $delivery) {
            if ($delivery->succeeded()) {
                fwrite(STDOUT, "{$delivery->channel}: delivered\n");
                continue;
            }
            $failed = true;
            fwrite(STDERR, "{$delivery->channel}: failed — {$delivery->error}\n");
        }

        return $failed ? 1 : 0;
    }

    /** @param array<int, string> $args */
    private function newsletterDispatch(array $args): int
    {
        $slug = $args[0] ?? '';
        if ($slug === '') {
            return $this->usage('newsletter:dispatch <post-slug>');
        }
        $post = $this->app->make(Repository::class)->findPost($slug);
        if ($post === null || !$post->isPublished()) {
            fwrite(STDERR, "Published post [{$slug}] not found.\n");
            return 1;
        }
        try {
            $result = $this->app->make(NewsletterDispatcher::class)->dispatch($post);
            fwrite(STDOUT, "{$result['queued']} newsletter message(s) queued.\n");
            return 0;
        } catch (\Throwable $error) {
            fwrite(STDERR, $error->getMessage() . "\n");
            return 1;
        }
    }

    /** @param array<int, string> $args */
    private function mailWork(array $args): int
    {
        $limit = isset($args[0]) ? max(1, (int) $args[0]) : 50;
        try {
            $result = $this->app->make(MailQueue::class)->work($limit);
            fwrite(STDOUT, sprintf(
                "Mail queue: %d processed, %d delivered, %d failed.\n",
                $result['processed'],
                $result['delivered'],
                $result['failed'],
            ));
            return $result['failed'] > 0 ? 1 : 0;
        } catch (\Throwable $error) {
            fwrite(STDERR, $error->getMessage() . "\n");
            return 1;
        }
    }

    private function resendWebhooksCheck(): int
    {
        $secret = (string) $this->app->config()->get('mail.resend_webhook_secret', '');
        if (!str_starts_with($secret, 'whsec_')) {
            fwrite(STDERR, "RESEND_WEBHOOK_SECRET is not configured.\n");
            return 1;
        }

        $path = $this->app->storagePath('distribution/resend/webhooks');
        $writable = $path;
        while (!is_dir($writable) && dirname($writable) !== $writable) {
            $writable = dirname($writable);
        }
        if (!is_writable($writable)) {
            fwrite(STDERR, "Resend webhook storage is not writable.\n");
            return 1;
        }

        $events = count(glob($path . '/*.json') ?: []);
        fwrite(STDOUT, "Resend webhooks are ready. {$events} event(s) recorded.\n");
        return 0;
    }

    private function threadsSync(): int
    {
        if ($this->app->make(DiscussionManager::class)->resolve('threads')->key() !== 'threads') {
            fwrite(STDERR, "Threads is disabled or unavailable in Discussion settings.\n");
            return 1;
        }

        try {
            $result = $this->app->make(ThreadsReplySync::class)->sync();
            fwrite(STDOUT, sprintf(
                "Threads replies: %d post(s) synced, %d repl%s cached, %d failed.\n",
                $result['posts'],
                $result['replies'],
                $result['replies'] === 1 ? 'y' : 'ies',
                $result['failed'],
            ));
            return $result['failed'] > 0 ? 1 : 0;
        } catch (\Throwable $error) {
            fwrite(STDERR, $error->getMessage() . "\n");
            return 1;
        }
    }

    private function threadsEngagementSync(): int
    {
        if ($this->app->make(DiscussionManager::class)->resolve('threads')->key() !== 'threads') {
            fwrite(STDERR, "Threads is disabled or unavailable in Discussion settings.\n");
            return 1;
        }

        try {
            $result = $this->app->make(ThreadsEngagementSync::class)->sync();
            fwrite(STDOUT, sprintf(
                "Threads engagement: %d post(s) synced, %d failed.\n",
                $result['posts'],
                $result['failed'],
            ));
            return $result['failed'] > 0 ? 1 : 0;
        } catch (\Throwable $error) {
            fwrite(STDERR, $error->getMessage() . "\n");
            return 1;
        }
    }

    private function analyticsCheck(): int
    {
        $store = $this->app->make(AnalyticsStore::class);
        $secret = (string) $this->app->config()->get('analytics.secret', '');
        if (!$store->available()) {
            fwrite(STDERR, "pdo_sqlite is not available.\n");
            return 1;
        }
        if ($secret === '') {
            fwrite(STDERR, "ANALYTICS_SECRET or APP_KEY is not configured.\n");
            return 1;
        }

        try {
            $store->summary();
            fwrite(STDOUT, "Analytics is ready.\n");
            return 0;
        } catch (\Throwable $error) {
            fwrite(STDERR, $error->getMessage() . "\n");
            return 1;
        }
    }

    private function analyticsPrune(): int
    {
        try {
            $days = (int) $this->app->config()->get('analytics.retention_days', 400);
            $deleted = $this->app->make(AnalyticsStore::class)->prune($days);
            fwrite(STDOUT, "Pruned {$deleted} analytics visit(s) older than {$days} days.\n");
            return 0;
        } catch (\Throwable $error) {
            fwrite(STDERR, $error->getMessage() . "\n");
            return 1;
        }
    }

    private function seoCheck(): int
    {
        $summary = $this->app->make(SeoChecker::class)->check();
        if ($summary->passed()) {
            fwrite(STDOUT, "SEO checks passed.\n");
            return 0;
        }

        fwrite(STDERR, count($summary->issues) . " SEO issue(s):\n");
        foreach ($summary->issues as $issue) {
            fwrite(STDERR, "  {$issue->slug} [{$issue->type}] {$issue->message}\n");
        }

        return 1;
    }

    private function revisionsList(array $args): int
    {
        $slug = $args[0] ?? '';
        if ($slug === '') {
            return $this->usage('revisions:list <slug>');
        }

        foreach ($this->app->make(RevisionStore::class)->all($slug) as $path) {
            fwrite(STDOUT, $path . "\n");
        }

        return 0;
    }

    private function draft(string $slug): ?\Katakata\Content\Draft
    {
        $draft = $slug === '' ? null : $this->app->make(Repository::class)->findDraft($slug);
        if ($draft === null) {
            fwrite(STDERR, "Draft [{$slug}] not found.\n");
        }

        return $draft;
    }

    private function usage(string $usage): int
    {
        fwrite(STDERR, "Usage: php bin/katakata {$usage}\n");
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

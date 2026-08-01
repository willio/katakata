<?php

declare(strict_types=1);

namespace Katakata\Dashboard;

use Katakata\Settings\SettingsStore;
use RuntimeException;

final class DashboardSettings
{
    /** @var array<string, array<string, scalar|null>> */
    private array $defaults;

    /** @param array<string, array<string, scalar|null>> $defaults */
    public function __construct(
        private readonly SettingsStore $store,
        array $defaults = [],
        private readonly bool $threadsConfigured = false,
    ) {
        $this->defaults = $defaults;
    }

    /** @return array<string, array<string, scalar|null>> */
    public function all(): array
    {
        $stored = $this->store->all();
        $settings = $this->defaults;

        foreach ($stored as $section => $values) {
            $settings[$section] = array_replace($settings[$section] ?? [], $values);
        }

        return $settings;
    }

    /** @return array<string, scalar|null> */
    public function section(string $section): array
    {
        $settings = $this->all();

        if (!array_key_exists($section, $settings)) {
            throw new RuntimeException("Unknown settings section [{$section}].");
        }

        return $settings[$section];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, scalar|null>
     */
    public function update(string $section, array $input): array
    {
        $values = match ($section) {
            'publication' => $this->publication($input),
            'newsletter' => $this->newsletter($input),
            'discussion' => $this->discussion($input),
            'analytics' => $this->analytics($input),
            'appearance' => $this->appearance($input),
            default => throw new RuntimeException("Unknown settings section [{$section}]."),
        };

        $this->store->updateSection($section, $values);

        return $values;
    }

    /** @param array<string, mixed> $input @return array<string, scalar|null> */
    private function publication(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Publication name is required.');
        }

        return [
            'name' => $name,
            'description' => trim((string) ($input['description'] ?? '')),
            'default_author' => trim((string) ($input['default_author'] ?? '')),
        ];
    }

    /** @param array<string, mixed> $input @return array<string, scalar|null> */
    private function newsletter(array $input): array
    {
        return [
            'sender_label' => trim((string) ($input['sender_label'] ?? '')),
            'publish_by_default' => $this->boolean($input['publish_by_default'] ?? false),
        ];
    }

    /** @param array<string, mixed> $input @return array<string, scalar|null> */
    private function discussion(array $input): array
    {
        $provider = trim((string) ($input['provider'] ?? 'none'));
        if (!in_array($provider, ['none', 'native', 'threads'], true)) {
            throw new RuntimeException('Discussion provider is invalid.');
        }
        if ($provider === 'threads' && !$this->threadsConfigured) {
            throw new RuntimeException('Threads requires THREADS_USER_ID and THREADS_ACCESS_TOKEN.');
        }

        return [
            'provider' => $provider,
            'enabled_by_default' => $this->boolean($input['enabled_by_default'] ?? false),
        ];
    }

    /** @param array<string, mixed> $input @return array<string, scalar|null> */
    private function analytics(array $input): array
    {
        $period = trim((string) ($input['dashboard_period'] ?? '30d'));
        if (!in_array($period, ['7d', '30d', '90d'], true)) {
            throw new RuntimeException('Analytics dashboard period is invalid.');
        }

        return ['dashboard_period' => $period];
    }

    /** @param array<string, mixed> $input @return array<string, scalar|null> */
    private function appearance(array $input): array
    {
        $theme = trim((string) ($input['theme'] ?? 'default'));
        if (!in_array($theme, ['default', 'warm', 'slate'], true)) {
            throw new RuntimeException('Appearance theme is invalid.');
        }

        return ['theme' => $theme];
    }

    private function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}

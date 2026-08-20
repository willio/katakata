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

        $stored = $this->all()['discussion'] ?? [];
        $userId = trim((string) ($input['threads_user_id'] ?? $stored['threads_user_id'] ?? ''));
        $tokenSecret = trim((string) (
            $input['threads_token_secret'] ?? $stored['threads_token_secret'] ?? 'THREADS_ACCESS_TOKEN'
        ));
        if (preg_match('/^[A-Z][A-Z0-9_]*$/', $tokenSecret) !== 1) {
            throw new RuntimeException('Threads token secret name is invalid.');
        }

        if ($provider === 'threads') {
            $tokenValue = getenv($tokenSecret);
            $hasToken = $this->threadsConfigured
                || (is_string($tokenValue) && trim($tokenValue) !== '');
            $hasUserId = $this->threadsConfigured || $userId !== '';
            if (!$hasUserId || !$hasToken) {
                throw new RuntimeException('Threads requires THREADS_USER_ID and THREADS_ACCESS_TOKEN.');
            }
        }

        return [
            'provider' => $provider,
            'enabled_by_default' => $this->boolean($input['enabled_by_default'] ?? false),
            'threads_user_id' => $userId,
            'threads_token_secret' => $tokenSecret,
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
        $current = $this->all()['appearance'] ?? [];
        $theme = trim((string) ($input['theme'] ?? $current['theme'] ?? 'default'));
        if (!in_array($theme, ['default', 'warm', 'slate'], true)) {
            throw new RuntimeException('Appearance theme is invalid.');
        }

        $style = trim((string) ($input['button_style'] ?? 'regular'));
        if (!in_array($style, ['regular', 'pill'], true)) {
            throw new RuntimeException('Appearance button style is invalid.');
        }

        return [
            'theme' => $theme,
            'button_style' => $style,
        ];
    }

    private function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}

<?php

namespace App\Support;

final class DeployInstanceRegistry
{
    /**
     * @return array{repo_root?: string, instances: list<array<string, mixed>>}
     */
    public static function load(?string $path = null): array
    {
        $path ??= base_path('deploy/instances.json');

        if (! is_file($path)) {
            return ['instances' => []];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new \RuntimeException("Invalid JSON in {$path}");
        }

        $instances = $decoded['instances'] ?? [];

        if (! is_array($instances)) {
            throw new \RuntimeException("instances must be an array in {$path}");
        }

        return [
            'repo_root' => isset($decoded['repo_root']) ? (string) $decoded['repo_root'] : null,
            'instances' => array_values(array_filter($instances, 'is_array')),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function enabledInstances(?string $path = null): array
    {
        $config = self::load($path);

        return array_values(array_filter(
            $config['instances'],
            static fn (array $instance): bool => (bool) ($instance['enabled'] ?? true)
                && filled($instance['path'] ?? null)
                && filled($instance['app_url'] ?? null),
        ));
    }

    /**
     * @param  array<string, mixed>  $instance
     */
    public static function resolvePath(array $instance, ?string $repoRoot = null): string
    {
        $path = (string) ($instance['path'] ?? '');

        if ($path === '' || $path === '.') {
            return $repoRoot ?? base_path();
        }

        if (! str_starts_with($path, '/')) {
            return rtrim($repoRoot ?? base_path(), '/').'/'.ltrim($path, '/');
        }

        return $path;
    }
}

<?php

namespace App\Support;

/**
 * Canonical identifiers for data imported from the legacy online billing portal.
 * Reads still accept the deprecated {@see LEGACY_IMPORT_SOURCE} value in stored rows.
 */
final class LegacyPortalSource
{
    public const IMPORT_SOURCE = 'legacy_portal';

    /** @deprecated Stored import_source on rows created before the code rename */
    public const LEGACY_IMPORT_SOURCE = 'isp_digital';

    /**
     * @return list<string>
     */
    public static function importSourceValues(): array
    {
        return [self::IMPORT_SOURCE, self::LEGACY_IMPORT_SOURCE];
    }

    public static function isImportedSource(?string $importSource): bool
    {
        return in_array($importSource, self::importSourceValues(), true);
    }

    public static function metaKey(string $suffix): string
    {
        return 'legacy_portal_'.$suffix;
    }

    /**
     * Latest legacy-portal row snapshot stored on the customer (either meta key).
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public static function rawSnapshot(array $meta): array
    {
        $current = $meta[self::metaKey('raw')] ?? null;
        if (is_array($current) && $current !== []) {
            return $current;
        }

        $legacy = $meta[self::LEGACY_IMPORT_SOURCE.'_raw'] ?? null;

        return is_array($legacy) ? $legacy : [];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public static function rawSnapshotWithLegacyKey(array $meta): array
    {
        $raw = self::rawSnapshot($meta);
        if ($raw === []) {
            return $meta;
        }

        $meta[self::metaKey('raw')] = $raw;

        return $meta;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function readMeta(array $meta, string $suffix, mixed $default = null): mixed
    {
        $key = self::metaKey($suffix);
        if (array_key_exists($key, $meta)) {
            return $meta[$key];
        }

        $legacyKey = self::LEGACY_IMPORT_SOURCE.'_'.$suffix;

        return $meta[$legacyKey] ?? $default;
    }
}

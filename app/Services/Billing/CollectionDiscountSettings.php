<?php

namespace App\Services\Billing;

use App\Models\AppSetting;
use App\Models\User;

final class CollectionDiscountSettings
{
    public const SETTINGS_KEY = 'billing.collection_discount';

    /**
     * @return array{
     *   enabled: bool,
     *   require_note_on_partial: bool,
     *   require_note_on_discount: bool,
     *   allow_custom_amount: bool,
     *   max_discount_bdt: float,
     *   max_discount_percent_of_due: float,
     *   presets: list<array{id: string, label: string, type: string, amount: float, max_bdt?: float}>
     * }
     */
    public static function get(): array
    {
        $defaults = (array) config('billing.collection_discount', []);
        $stored = AppSetting::getStoredValue(self::SETTINGS_KEY);
        if ($stored !== null && $stored !== '') {
            $decoded = json_decode($stored, true);
            if (is_array($decoded)) {
                return self::normalize(array_merge($defaults, $decoded));
            }
        }

        return self::normalize($defaults);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function save(array $payload): void
    {
        AppSetting::putValue(self::SETTINGS_KEY, json_encode(self::normalize($payload), JSON_THROW_ON_ERROR));
    }

    public static function isEnabled(): bool
    {
        return (bool) self::get()['enabled'];
    }

    public static function userCanApplyDiscount(?User $user = null): bool
    {
        $user ??= auth()->user();
        if ($user === null) {
            return false;
        }

        if (! self::isEnabled()) {
            return false;
        }

        if ($user->hasAnyRole(['super-admin', 'isp-admin', 'admin'])) {
            return true;
        }

        return $user->can('billing.discount');
    }

    /**
     * @return array<string, string> preset id => label
     */
    public static function presetOptions(): array
    {
        $options = [];
        foreach (self::get()['presets'] as $preset) {
            $options[$preset['id']] = $preset['label'];
        }

        return $options;
    }

    /**
     * @return array{id: string, label: string, type: string, amount: float, max_bdt?: float}|null
     */
    public static function findPreset(string $presetId): ?array
    {
        foreach (self::get()['presets'] as $preset) {
            if ($preset['id'] === $presetId) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * Resolve discount BDT for an invoice balance (before discount is applied).
     */
    public static function resolveDiscountBdt(
        ?string $presetId,
        string $customAmount,
        float $balanceDue,
    ): float {
        if ($balanceDue <= 0 || ! self::isEnabled()) {
            return 0.0;
        }

        $settings = self::get();
        $discount = 0.0;

        if ($presetId !== '' && $presetId !== 'none') {
            $preset = self::findPreset($presetId);
            if ($preset !== null) {
                $discount = match ($preset['type']) {
                    'percent' => round($balanceDue * ($preset['amount'] / 100), 2),
                    default => round((float) $preset['amount'], 2),
                };
                if (isset($preset['max_bdt']) && $preset['max_bdt'] > 0) {
                    $discount = min($discount, (float) $preset['max_bdt']);
                }
            }
        } elseif ($settings['allow_custom_amount'] && is_numeric($customAmount)) {
            $discount = round(max(0.0, (float) $customAmount), 2);
        }

        if ($discount <= 0) {
            return 0.0;
        }

        $capPercent = (float) $settings['max_discount_percent_of_due'];
        if ($capPercent > 0) {
            $discount = min($discount, round($balanceDue * ($capPercent / 100), 2));
        }

        $capBdt = (float) $settings['max_discount_bdt'];
        if ($capBdt > 0) {
            $discount = min($discount, $capBdt);
        }

        return min($discount, $balanceDue);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{
     *   enabled: bool,
     *   require_note_on_partial: bool,
     *   require_note_on_discount: bool,
     *   allow_custom_amount: bool,
     *   max_discount_bdt: float,
     *   max_discount_percent_of_due: float,
     *   presets: list<array{id: string, label: string, type: string, amount: float, max_bdt?: float}>
     * }
     */
    private static function normalize(array $raw): array
    {
        $presets = [];
        foreach ((array) ($raw['presets'] ?? []) as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ('preset_'.($i + 1))));
            $label = trim((string) ($row['label'] ?? $id));
            $type = in_array($row['type'] ?? 'fixed', ['fixed', 'percent'], true) ? $row['type'] : 'fixed';
            $amount = round(max(0.0, (float) ($row['amount'] ?? 0)), 2);
            if ($id === '' || $label === '' || $amount <= 0) {
                continue;
            }
            $preset = [
                'id' => $id,
                'label' => $label,
                'type' => $type,
                'amount' => $amount,
            ];
            $maxBdt = round(max(0.0, (float) ($row['max_bdt'] ?? 0)), 2);
            if ($maxBdt > 0) {
                $preset['max_bdt'] = $maxBdt;
            }
            $presets[] = $preset;
        }

        return [
            'enabled' => (bool) ($raw['enabled'] ?? true),
            'require_note_on_partial' => (bool) ($raw['require_note_on_partial'] ?? true),
            'require_note_on_discount' => (bool) ($raw['require_note_on_discount'] ?? true),
            'allow_custom_amount' => (bool) ($raw['allow_custom_amount'] ?? true),
            'max_discount_bdt' => round(max(0.0, (float) ($raw['max_discount_bdt'] ?? 500)), 2),
            'max_discount_percent_of_due' => round(max(0.0, (float) ($raw['max_discount_percent_of_due'] ?? 50)), 2),
            'presets' => $presets,
        ];
    }
}

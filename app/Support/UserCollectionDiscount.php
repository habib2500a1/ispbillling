<?php

namespace App\Support;

use App\Models\User;

final class UserCollectionDiscount
{
    /**
     * @return array{enabled: bool, max_discount_bdt: ?float, max_discount_percent_of_due: ?float}
     */
    public static function prefs(?User $user): array
    {
        if ($user === null) {
            return ['enabled' => false, 'max_discount_bdt' => null, 'max_discount_percent_of_due' => null];
        }

        if ($user->hasAnyRole(['super-admin', 'isp-admin', 'admin'])) {
            return ['enabled' => true, 'max_discount_bdt' => null, 'max_discount_percent_of_due' => null];
        }

        $raw = is_array($user->dashboard_preferences) ? $user->dashboard_preferences : [];
        $cd = is_array($raw['collection_discount'] ?? null) ? $raw['collection_discount'] : [];

        $enabled = array_key_exists('enabled', $cd)
            ? (bool) $cd['enabled']
            : $user->can('billing.discount');

        $maxBdt = isset($cd['max_discount_bdt']) && is_numeric($cd['max_discount_bdt'])
            ? round(max(0.0, (float) $cd['max_discount_bdt']), 2)
            : null;
        $maxPercent = isset($cd['max_discount_percent_of_due']) && is_numeric($cd['max_discount_percent_of_due'])
            ? round(max(0.0, (float) $cd['max_discount_percent_of_due']), 2)
            : null;

        return [
            'enabled' => $enabled,
            'max_discount_bdt' => $maxBdt > 0 ? $maxBdt : null,
            'max_discount_percent_of_due' => $maxPercent > 0 ? min(100.0, $maxPercent) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function mergeIntoDashboardPreferences(User $user, array $input): array
    {
        $prefs = is_array($user->dashboard_preferences) ? $user->dashboard_preferences : [];
        $prefs['collection_discount'] = [
            'enabled' => (bool) ($input['enabled'] ?? false),
            'max_discount_bdt' => filled($input['max_discount_bdt'] ?? null)
                ? round(max(0.0, (float) $input['max_discount_bdt']), 2)
                : null,
            'max_discount_percent_of_due' => filled($input['max_discount_percent_of_due'] ?? null)
                ? round(max(0.0, min(100.0, (float) $input['max_discount_percent_of_due'])), 2)
                : null,
        ];

        return $prefs;
    }
}

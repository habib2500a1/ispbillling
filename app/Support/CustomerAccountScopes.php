<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared filters for subscriber list pages (active / expired / left).
 */
final class CustomerAccountScopes
{
    public static function applyActive(Builder $query): Builder
    {
        return $query
            ->where('status', CustomerStatus::ACTIVE)
            ->where(function (Builder $q): void {
                $q->whereNull('service_expires_at')
                    ->orWhereDate('service_expires_at', '>=', now()->toDateString());
            });
    }

    public static function applyExpired(Builder $query): Builder
    {
        return $query
            ->where('status', '!=', CustomerStatus::TERMINATED)
            ->where(function (Builder $q): void {
                static::notLegacyLeft($q);
            })
            ->where(function (Builder $q): void {
                $q->where('status', CustomerStatus::EXPIRED)
                    ->orWhere(function (Builder $q2): void {
                        $q2->whereNotNull('service_expires_at')
                            ->whereDate('service_expires_at', '<', now()->toDateString());
                    });
            });
    }

    public static function applyLeft(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('status', CustomerStatus::TERMINATED)
                ->orWhere(function (Builder $q2): void {
                    static::legacyLeft($q2);
                });
        });
    }

    public static function excludeLegacyLeft(Builder $query): Builder
    {
        return $query->where(fn (Builder $q): Builder => static::notLegacyLeft($q));
    }

    public static function legacyLeft(Builder $query): Builder
    {
        return $query
            ->whereRaw("LOWER(COALESCE(meta->'isp_digital_raw'->>'Status', '')) LIKE ?", ['%left%'])
            ->orWhereRaw("LOWER(COALESCE(meta->'isp_digital_raw'->>'ShortStatus', '')) = ?", ['left']);
    }

    public static function notLegacyLeft(Builder $query): Builder
    {
        return $query
            ->whereRaw("LOWER(COALESCE(meta->'isp_digital_raw'->>'Status', '')) NOT LIKE ?", ['%left%'])
            ->whereRaw("LOWER(COALESCE(meta->'isp_digital_raw'->>'ShortStatus', '')) != ?", ['left']);
    }
}

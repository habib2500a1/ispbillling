<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared filters for subscriber list pages (active / expired / left / due).
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

    /**
     * Subscribers with open invoice balance or legacy portal due snapshot.
     */
    public static function applyWithBalanceDue(Builder $query, ?int $tenantId = null): Builder
    {
        $tenantId ??= \App\Support\TenantResolver::currentTenantId();

        return $query
            ->where('status', '!=', CustomerStatus::TERMINATED)
            ->whereHas('invoices', function (Builder $inv) use ($tenantId): void {
                if ($tenantId !== null) {
                    $inv->where('tenant_id', $tenantId);
                }
                $inv->whereIn('status', CustomerBalanceDue::OPEN_INVOICE_STATUSES)
                    ->whereRaw('(total - amount_paid) > 0.009');
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
        return $query->where(function (Builder $q): void {
            $q->whereRaw(self::legacyRawStatusSql().' LIKE ?', ['%left%'])
                ->orWhereRaw(self::legacyRawShortStatusSql().' = ?', ['left']);
        });
    }

    public static function notLegacyLeft(Builder $query): Builder
    {
        return $query
            ->whereRaw(self::legacyRawStatusSql().' NOT LIKE ?', ['%left%'])
            ->whereRaw(self::legacyRawShortStatusSql().' != ?', ['left']);
    }

    private static function legacyRawStatusSql(): string
    {
        return "LOWER(COALESCE(meta->'legacy_portal_raw'->>'Status', meta->'isp_digital_raw'->>'Status', ''))";
    }

    private static function legacyRawShortStatusSql(): string
    {
        return "LOWER(COALESCE(meta->'legacy_portal_raw'->>'ShortStatus', meta->'isp_digital_raw'->>'ShortStatus', ''))";
    }
}

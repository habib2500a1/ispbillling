<?php

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOltOnuInventoryCommand extends Command
{
    protected $signature = 'isp:cleanup-olt-onu-inventory {--olt= : OLT ID} {--dry-run : Show counts only}';

    protected $description = 'Remove duplicate SUB-* placeholder ONUs and invalid RX readings';

    public function handle(): int
    {
        $oltId = $this->option('olt') ? (int) $this->option('olt') : null;
        $dryRun = (bool) $this->option('dry-run');

        $base = Device::query()->withoutGlobalScopes()->where('type', 'onu');
        if ($oltId) {
            $base->where('olt_id', $oltId);
        }

        $placeholders = (clone $base)->where('serial_number', 'like', 'SUB-%')
            ->where(function ($q): void {
                $q->whereNull('meta->last_bdcom_sync')
                    ->orWhereRaw("(meta->>'last_bdcom_sync') IS NULL");
            });

        $placeholderCount = $placeholders->count();
        $this->line("SUB placeholders (no BDCOM sync): {$placeholderCount}");

        $invalidRx = (clone $base)->where(function ($q): void {
            $q->where('rx_power_dbm', '<', -60)
                ->orWhere('rx_power_dbm', '>', 10);
        });
        $invalidRxCount = $invalidRx->count();
        $this->line("Invalid RX readings: {$invalidRxCount}");

        if ($dryRun) {
            return self::SUCCESS;
        }

        $deletedPlaceholders = $placeholders->delete();
        $this->info("Deleted {$deletedPlaceholders} placeholder ONU(s).");

        $fixedRx = (clone $base)->where(function ($q): void {
            $q->where('rx_power_dbm', '<', -60)
                ->orWhere('rx_power_dbm', '>', 10);
        })->update(['rx_power_dbm' => null]);
        $this->info("Cleared {$fixedRx} invalid RX value(s).");

        $oltFilter = $oltId ? 'AND olt_id = '.(int) $oltId : '';
        $dupes = DB::select("
            SELECT mac_address, olt_id, COUNT(*) as c
            FROM devices
            WHERE type = 'onu' AND mac_address IS NOT NULL {$oltFilter}
            GROUP BY mac_address, olt_id
            HAVING COUNT(*) > 1
        ");

        $removedDupes = 0;
        foreach ($dupes as $row) {
            $ids = Device::query()
                ->withoutGlobalScopes()
                ->where('type', 'onu')
                ->where('olt_id', $row->olt_id)
                ->where('mac_address', $row->mac_address)
                ->orderByDesc('id')
                ->pluck('id');
            $keep = $ids->shift();
            $removedDupes += Device::query()->whereIn('id', $ids)->delete();
        }
        $this->info("Removed {$removedDupes} duplicate MAC ONU row(s).");

        return self::SUCCESS;
    }
}

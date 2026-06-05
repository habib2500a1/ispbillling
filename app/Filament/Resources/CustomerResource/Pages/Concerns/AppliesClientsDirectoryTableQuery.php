<?php

namespace App\Filament\Resources\CustomerResource\Pages\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Reliable global search for the Sheba-style clients directory table.
 */
trait AppliesClientsDirectoryTableQuery
{
    protected function applySearchToTableQuery(Builder $query): Builder
    {
        $search = trim((string) ($this->getTableSearch() ?? ''));

        if ($search === '') {
            return $query;
        }

        $like = '%'.$search.'%';
        $table = $query->getModel()->getTable();
        $driver = $query->getConnection()->getDriverName();

        return $query->where(function (Builder $searchQuery) use ($like, $table, $driver): void {
            if ($driver === 'pgsql') {
                $searchQuery
                    ->where("{$table}.name", 'ilike', $like)
                    ->orWhere("{$table}.customer_code", 'ilike', $like)
                    ->orWhere("{$table}.phone", 'ilike', $like)
                    ->orWhere("{$table}.email", 'ilike', $like)
                    ->orWhere("{$table}.mikrotik_secret_name", 'ilike', $like)
                    ->orWhere("{$table}.radius_username", 'ilike', $like)
                    ->orWhere("{$table}.address", 'ilike', $like)
                    ->orWhereHas('zone', fn (Builder $zoneQuery): Builder => $zoneQuery->where('name', 'ilike', $like))
                    ->orWhereHas('area', fn (Builder $areaQuery): Builder => $areaQuery->where('name', 'ilike', $like));

                return;
            }

            $searchQuery
                ->whereRaw("LOWER({$table}.name) LIKE LOWER(?)", [$like])
                ->orWhereRaw("LOWER({$table}.customer_code) LIKE LOWER(?)", [$like])
                ->orWhereRaw("LOWER({$table}.phone) LIKE LOWER(?)", [$like])
                ->orWhereRaw("LOWER({$table}.email) LIKE LOWER(?)", [$like])
                ->orWhereRaw("LOWER({$table}.mikrotik_secret_name) LIKE LOWER(?)", [$like])
                ->orWhereRaw("LOWER({$table}.radius_username) LIKE LOWER(?)", [$like])
                ->orWhereRaw("LOWER({$table}.address) LIKE LOWER(?)", [$like])
                ->orWhereHas('zone', fn (Builder $zoneQuery): Builder => $zoneQuery->whereRaw('LOWER(name) LIKE LOWER(?)', [$like]))
                ->orWhereHas('area', fn (Builder $areaQuery): Builder => $areaQuery->whereRaw('LOWER(name) LIKE LOWER(?)', [$like]));
        });
    }
}

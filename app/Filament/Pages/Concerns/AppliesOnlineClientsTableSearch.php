<?php

namespace App\Filament\Pages\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Reliable global search for the online-clients monitoring table.
 */
trait AppliesOnlineClientsTableSearch
{
    protected function syncOnlineClientsTableSearchState(): void
    {
        if ($this->getTable()->shouldDeselectAllRecordsWhenFiltered()) {
            $this->deselectAllTableRecords();
        }

        $this->resetPage();
        $this->flushCachedTableRecords();
        $this->tableResultsEpoch++;
    }

    protected function applySearchToTableQuery(Builder $query): Builder
    {
        $search = trim((string) ($this->getTableSearch() ?? ''));

        if ($search === '') {
            return $query;
        }

        $table = $query->getModel()->getTable();
        $driver = $query->getConnection()->getDriverName();

        foreach ($this->extractTableSearchWords($search) as $word) {
            $like = '%'.$word.'%';

            $query->where(function (Builder $searchQuery) use ($like, $table, $driver): void {
                if ($driver === 'pgsql') {
                    $searchQuery
                        ->where("{$table}.name", 'ilike', $like)
                        ->orWhere("{$table}.customer_code", 'ilike', $like)
                        ->orWhere("{$table}.phone", 'ilike', $like)
                        ->orWhere("{$table}.mikrotik_secret_name", 'ilike', $like)
                        ->orWhere("{$table}.radius_username", 'ilike', $like)
                        ->orWhereHas('activePppSession', fn (Builder $sessionQuery): Builder => $sessionQuery
                            ->where('framed_ip', 'ilike', $like)
                            ->orWhere('caller_id', 'ilike', $like))
                        ->orWhereHas('mikrotikServer', fn (Builder $routerQuery): Builder => $routerQuery
                            ->where('name', 'ilike', $like)
                            ->orWhere('host', 'ilike', $like))
                        ->orWhereHas('zone', fn (Builder $zoneQuery): Builder => $zoneQuery->where('name', 'ilike', $like))
                        ->orWhereHas('subzone', fn (Builder $subzoneQuery): Builder => $subzoneQuery->where('name', 'ilike', $like))
                        ->orWhereHas('package', fn (Builder $packageQuery): Builder => $packageQuery->where('name', 'ilike', $like));

                    return;
                }

                $searchQuery
                    ->whereRaw("LOWER({$table}.name) LIKE LOWER(?)", [$like])
                    ->orWhereRaw("LOWER({$table}.customer_code) LIKE LOWER(?)", [$like])
                    ->orWhereRaw("LOWER({$table}.phone) LIKE LOWER(?)", [$like])
                    ->orWhereRaw("LOWER({$table}.mikrotik_secret_name) LIKE LOWER(?)", [$like])
                    ->orWhereRaw("LOWER({$table}.radius_username) LIKE LOWER(?)", [$like])
                    ->orWhereHas('activePppSession', fn (Builder $sessionQuery): Builder => $sessionQuery
                        ->whereRaw('LOWER(framed_ip) LIKE LOWER(?)', [$like])
                        ->orWhereRaw('LOWER(caller_id) LIKE LOWER(?)', [$like]))
                    ->orWhereHas('mikrotikServer', fn (Builder $routerQuery): Builder => $routerQuery
                        ->whereRaw('LOWER(name) LIKE LOWER(?)', [$like])
                        ->orWhereRaw('LOWER(host) LIKE LOWER(?)', [$like]))
                    ->orWhereHas('zone', fn (Builder $zoneQuery): Builder => $zoneQuery->whereRaw('LOWER(name) LIKE LOWER(?)', [$like]))
                    ->orWhereHas('subzone', fn (Builder $subzoneQuery): Builder => $subzoneQuery->whereRaw('LOWER(name) LIKE LOWER(?)', [$like]))
                    ->orWhereHas('package', fn (Builder $packageQuery): Builder => $packageQuery->whereRaw('LOWER(name) LIKE LOWER(?)', [$like]));
            });
        }

        return $query;
    }
}

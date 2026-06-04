<?php

namespace App\Livewire\Filament;

use Filament\Livewire\GlobalSearch as FilamentGlobalSearch;

/**
 * Absorbs stray $data updates when Alpine x-persist restores an old topbar
 * Global Search snapshot after globalSearch(false) — prevents edit-form 500s.
 */
class SafeGlobalSearch extends FilamentGlobalSearch
{
    /** @var array<string, mixed> */
    public array $data = [];

    /** @param  array<string, mixed>  $value */
    public function updatedData(array $value): void
    {
        // Intentionally ignored — real form data belongs on EditRecord, not global search.
    }
}

<?php

namespace App\Livewire\Filament;

use Filament\Livewire\GlobalSearch as FilamentGlobalSearch;

/**
 * Header global search — keeps Filament Livewire dropdown; ignores stray snapshot keys.
 */
class SafeGlobalSearch extends FilamentGlobalSearch
{
    public function updatedSearch(): void
    {
        $this->search = trim((string) ($this->search ?? ''));
    }

    public function hydrate(): void
    {
        $this->search = trim((string) ($this->search ?? ''));
    }
}

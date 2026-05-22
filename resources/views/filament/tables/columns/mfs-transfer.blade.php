@php
    /** @var \App\Models\MfsSmsRecord $record */
    $record = $getRecord();
    $label = \App\Filament\Resources\MfsSmsRecordResource::transferColumnLabel($record);
    $tooltip = \App\Filament\Resources\MfsSmsRecordResource::transferColumnTooltip($record);
@endphp

<div class="flex justify-center">
    @if ($label)
        <button
            type="button"
            wire:click="mountTableAction('transferFromColumn', '{{ $record->getKey() }}')"
            @if ($tooltip) title="{{ $tooltip }}" @endif
            class="fi-badge flex items-center justify-center gap-x-1 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset fi-color-warning bg-warning-50 text-warning-700 ring-warning-600/10 dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/30 hover:bg-warning-100 dark:hover:bg-warning-400/20 cursor-pointer"
        >
            Transfer
        </button>
    @else
        <span class="text-gray-400 dark:text-gray-500">—</span>
    @endif
</div>

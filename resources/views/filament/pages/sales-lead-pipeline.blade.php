@php
    $columns = $this->columns;
    $staff = $this->staffOptions;
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-2xl border border-violet-200 bg-gradient-to-br from-violet-50 via-white to-fuchsia-50/40 p-5 dark:border-violet-900/40 dark:from-violet-950/30 dark:via-gray-900">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Sales lead pipeline</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Drag cards to update stage. Convert won leads from the list view.</p>
        </div>

        <div class="flex flex-wrap gap-3">
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase text-gray-500">Assignee</label>
                <select wire:model.live="filterAssignee" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
                    <option value="">All staff</option>
                    @foreach ($staff as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <a href="{{ \App\Filament\Resources\SalesLeadResource::getUrl('index') }}" class="text-sm font-semibold text-violet-600 hover:underline">Table view →</a>
            </div>
        </div>

        <div class="kanban-grid grid gap-4 lg:grid-cols-3 xl:grid-cols-5">
            @foreach ($columns as $status => $column)
                <div
                    class="kanban-column flex min-h-[16rem] flex-col rounded-xl border p-3 {{ $column['color'] }}"
                    @dragover.prevent
                    @drop.prevent="$wire.moveLead(parseInt($event.dataTransfer.getData('leadId')), '{{ $status }}')"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-bold">{{ $column['label'] }}</h3>
                        <span class="rounded-full bg-white/80 px-2 py-0.5 text-xs font-bold shadow-sm dark:bg-gray-800">{{ $column['leads']->count() }}</span>
                    </div>
                    <div class="flex flex-1 flex-col gap-2 overflow-y-auto">
                        @forelse ($column['leads'] as $lead)
                            <div
                                wire:key="lead-{{ $lead->id }}"
                                draggable="true"
                                @dragstart="$event.dataTransfer.setData('leadId', '{{ $lead->id }}')"
                                class="cursor-grab rounded-lg border bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                            >
                                <a href="{{ \App\Services\Sales\SalesLeadKanbanService::editUrl($lead) }}" class="font-semibold text-gray-900 hover:text-violet-600 dark:text-white">{{ $lead->name }}</a>
                                <p class="mt-1 text-xs text-gray-500">{{ $lead->phone }}</p>
                                @if ($lead->package)
                                    <p class="mt-1 text-[10px] text-violet-600">{{ $lead->package->name }}</p>
                                @endif
                                @if ($lead->next_follow_up_at)
                                    <p class="mt-1 text-[10px] {{ $lead->next_follow_up_at->isPast() ? 'font-semibold text-rose-600' : 'text-gray-400' }}">
                                        Follow-up {{ $lead->next_follow_up_at->format('d M H:i') }}
                                    </p>
                                @endif
                            </div>
                        @empty
                            <p class="py-4 text-center text-xs text-gray-400">No leads</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>

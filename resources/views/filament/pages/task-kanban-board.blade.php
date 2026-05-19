@php
    $columns = $this->columns;
    $staff = $this->staffOptions;
    $priorityColors = [
        'urgent' => 'bg-rose-100 text-rose-800 ring-rose-200',
        'high' => 'bg-amber-100 text-amber-900 ring-amber-200',
        'normal' => 'bg-slate-100 text-slate-700 ring-slate-200',
        'low' => 'bg-gray-100 text-gray-600 ring-gray-200',
    ];
    $columnThemes = [
        'pending' => 'border-slate-200 bg-slate-50/80 dark:border-slate-700 dark:bg-slate-900/50',
        'in_progress' => 'border-amber-200 bg-amber-50/60 dark:border-amber-900/50 dark:bg-amber-950/20',
        'done' => 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-900/50 dark:bg-emerald-950/20',
        'cancelled' => 'border-rose-200 bg-rose-50/50 dark:border-rose-900/40 dark:bg-rose-950/20',
    ];
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 via-white to-orange-50/40 p-5 shadow-sm dark:border-amber-900/40 dark:from-amber-950/30 dark:via-gray-900">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Staff task board</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Drag cards between columns to update status. Filters apply to all columns.</p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <form wire:submit="createTask" class="flex flex-wrap items-end gap-3">
                <div class="min-w-[12rem] flex-1">
                    <label class="mb-1 block text-xs font-semibold uppercase text-gray-500">Quick add</label>
                    <input type="text" wire:model="newTitle" placeholder="Task title…" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-950" required>
                    @error('newTitle') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase text-gray-500">Priority</label>
                    <select wire:model="newPriority" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-950">
                        <option value="low">Low</option>
                        <option value="normal">Normal</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase text-gray-500">Assign</label>
                    <select wire:model="newAssignee" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-950">
                        <option value="">—</option>
                        @foreach ($staff as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">Add to Pending</button>
            </form>
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
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase text-gray-500">Priority</label>
                <select wire:model.live="filterPriority" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
                    <option value="">All priorities</option>
                    <option value="urgent">Urgent</option>
                    <option value="high">High</option>
                    <option value="normal">Normal</option>
                    <option value="low">Low</option>
                </select>
            </div>
        </div>

        <div class="kanban-grid grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
            @foreach ($columns as $status => $column)
                <div
                    class="kanban-column flex min-h-[20rem] flex-col rounded-xl border p-3 {{ $columnThemes[$status] ?? '' }}"
                    x-data
                    @dragover.prevent="$el.classList.add('kanban-column--drag')"
                    @dragleave.prevent="$el.classList.remove('kanban-column--drag')"
                    @drop.prevent="$el.classList.remove('kanban-column--drag'); $wire.moveTask(parseInt($event.dataTransfer.getData('taskId')), '{{ $status }}')"
                >
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">{{ $column['label'] }}</h3>
                        <span class="rounded-full bg-white/80 px-2 py-0.5 text-xs font-bold text-gray-600 shadow-sm dark:bg-gray-800 dark:text-gray-300">{{ $column['tasks']->count() }}</span>
                    </div>

                    <div class="flex flex-1 flex-col gap-2 overflow-y-auto">
                        @forelse ($column['tasks'] as $task)
                            <div
                                wire:key="task-{{ $task->id }}"
                                draggable="true"
                                @dragstart="$event.dataTransfer.setData('taskId', '{{ $task->id }}')"
                                class="kanban-card cursor-grab rounded-lg border border-white/80 bg-white p-3 shadow-sm active:cursor-grabbing dark:border-gray-700 dark:bg-gray-900"
                            >
                                <a href="{{ \App\Services\Support\InternalTaskKanbanService::editUrl($task) }}" class="font-semibold text-gray-900 hover:text-amber-700 dark:text-white dark:hover:text-amber-300">{{ $task->title }}</a>
                                @if ($task->description)
                                    <p class="mt-1 line-clamp-2 text-xs text-gray-500">{{ $task->description }}</p>
                                @endif
                                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                    <span class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase ring-1 {{ $priorityColors[$task->priority] ?? $priorityColors['normal'] }}">{{ $task->priority }}</span>
                                    @if ($task->assignee)
                                        <span class="text-[10px] text-gray-500">{{ $task->assignee->name }}</span>
                                    @endif
                                </div>
                                @if ($task->due_at)
                                    <p class="mt-1 text-[10px] {{ $task->due_at->isPast() && $status !== 'done' ? 'font-semibold text-rose-600' : 'text-gray-400' }}">
                                        Due {{ $task->due_at->format('d M, H:i') }}
                                    </p>
                                @endif
                            </div>
                        @empty
                            <p class="py-6 text-center text-xs text-gray-400">Drop tasks here</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @push('styles')
        <style>
            .kanban-column--drag { outline: 2px dashed rgb(245 158 11); outline-offset: 2px; }
            .kanban-card:hover { box-shadow: 0 4px 12px rgb(0 0 0 / 0.08); }
        </style>
    @endpush
</x-filament-panels::page>

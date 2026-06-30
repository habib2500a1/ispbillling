<x-filament-panels::page>
    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
        <p class="font-semibold">Human-in-the-loop</p>
        <p class="mt-1">AI can propose bulk actions (e.g. suspend chronic defaulters). Nothing runs until an admin approves here.</p>
    </div>

    @if ($pendingActions->isEmpty())
        <div class="rounded-xl border border-gray-200 bg-white p-8 text-center text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
            No pending AI actions.
        </div>
    @else
        <div class="space-y-4">
            @foreach ($pendingActions as $action)
                <div wire:key="ai-action-{{ $action->id }}" class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ str_replace('_', ' ', $action->action_type) }}</p>
                            <p class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ $action->summary }}</p>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                Requested {{ $action->created_at?->diffForHumans() }}
                                @if ($action->requester)
                                    by {{ $action->requester->name }}
                                @endif
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <x-filament::button color="success" wire:click="approveAction({{ $action->id }})" wire:confirm="Approve and execute this action?">
                                Approve
                            </x-filament::button>
                            <x-filament::button color="danger" wire:click="rejectAction({{ $action->id }})" wire:confirm="Reject this action?">
                                Reject
                            </x-filament::button>
                        </div>
                    </div>

                    @if (is_array($action->preview['customers'] ?? null) && count($action->preview['customers']) > 0)
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-left text-gray-500">
                                        <th class="py-2 pr-4">Code</th>
                                        <th class="py-2">Name</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (array_slice($action->preview['customers'], 0, 10) as $customer)
                                        <tr class="border-t border-gray-100 dark:border-gray-800">
                                            <td class="py-2 pr-4 font-mono text-xs">{{ $customer['customer_code'] ?? '—' }}</td>
                                            <td class="py-2">{{ $customer['name'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if (count($action->preview['customers']) > 10)
                                <p class="mt-2 text-xs text-gray-500">+ {{ count($action->preview['customers']) - 10 }} more</p>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>

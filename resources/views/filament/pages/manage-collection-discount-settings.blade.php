<x-filament-panels::page>
    <form wire:submit="save" class="mx-auto max-w-3xl space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">General</h2>
            <div class="mt-4 space-y-3">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="enabled" class="rounded border-gray-300" />
                    Enable collection discounts at bill collection desk
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="require_note_on_partial" class="rounded border-gray-300" />
                    Require note when partial payment (amount &lt; due)
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="require_note_on_discount" class="rounded border-gray-300" />
                    Require note when any discount is applied
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="allow_custom_amount" class="rounded border-gray-300" />
                    Allow custom discount amount (besides presets)
                </label>
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-gray-500">Max discount per collection (BDT)</label>
                    <input type="number" step="0.01" min="0" wire:model="max_discount_bdt" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-gray-500">Max % of invoice due</label>
                    <input type="number" step="0.01" min="0" max="100" wire:model="max_discount_percent_of_due" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800" />
                </div>
            </div>
            <p class="mt-3 text-xs text-gray-500">
                Staff with permission <code class="text-xs">billing.discount</code> or admin roles can apply discounts at
                <a href="{{ \App\Filament\Pages\BillCollectionDesk::getUrl() }}" class="font-semibold text-teal-600 hover:underline">Bill collection desk</a>.
            </p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">Discount presets</h2>
                <button type="button" wire:click="addPreset" class="rounded-lg bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-700">
                    Add preset
                </button>
            </div>
            <ul class="mt-4 space-y-4">
                @foreach ($presets as $i => $preset)
                    <li wire:key="preset-{{ $i }}" class="rounded-lg border border-gray-100 p-4 dark:border-gray-800">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-bold uppercase text-gray-500">ID (slug)</label>
                                <input type="text" wire:model="presets.{{ $i }}.id" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800" />
                            </div>
                            <div>
                                <label class="text-xs font-bold uppercase text-gray-500">Label (shown to staff)</label>
                                <input type="text" wire:model="presets.{{ $i }}.label" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800" />
                            </div>
                            <div>
                                <label class="text-xs font-bold uppercase text-gray-500">Type</label>
                                <select wire:model.live="presets.{{ $i }}.type" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                    <option value="fixed">Fixed BDT</option>
                                    <option value="percent">Percent of due</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-bold uppercase text-gray-500">Amount (BDT or %)</label>
                                <input type="number" step="0.01" min="0.01" wire:model="presets.{{ $i }}.amount" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800" />
                            </div>
                            @if (($preset['type'] ?? '') === 'percent')
                                <div class="sm:col-span-2">
                                    <label class="text-xs font-bold uppercase text-gray-500">Max cap (BDT, optional)</label>
                                    <input type="number" step="0.01" min="0" wire:model="presets.{{ $i }}.max_bdt" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800" placeholder="e.g. 200" />
                                </div>
                            @endif
                        </div>
                        @if (count($presets) > 1)
                            <button type="button" wire:click="removePreset({{ $i }})" class="mt-2 text-xs font-semibold text-rose-600 hover:underline">Remove</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        <button type="submit" class="rounded-lg bg-teal-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-teal-700">
            Save settings
        </button>
    </form>
</x-filament-panels::page>

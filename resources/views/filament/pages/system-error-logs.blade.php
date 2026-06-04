<x-filament-panels::page>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                Lines to show
                <select wire:model.live="tailLines" class="ml-2 rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="100">100</option>
                    <option value="200">200</option>
                    <option value="500">500</option>
                    <option value="1000">1000</option>
                </select>
            </label>
            <x-filament::button tag="a" href="{{ url()->current() }}" color="gray" size="sm">Refresh</x-filament::button>
        </div>

        <pre class="max-h-[70vh] overflow-auto rounded-xl border border-gray-200 bg-gray-950 p-4 text-xs leading-relaxed text-gray-100 dark:border-gray-700">@foreach ($this->getLogLines() as $line){{ $line }}
@endforeach</pre>
    </div>
</x-filament-panels::page>

<x-filament-panels::page>
    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
        <p class="font-semibold">Approve partner wallet top-ups here</p>
        <p class="mt-1 text-amber-900/80 dark:text-amber-200/80">
            When you approve, the amount is added to the partner wallet immediately. Low or negative balance partners stay active once funded — commission due is shown for your reference (settle separately from the commission report).
        </p>
    </div>
    {{ $this->table }}
</x-filament-panels::page>

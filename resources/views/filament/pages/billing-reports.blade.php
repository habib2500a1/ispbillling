<x-filament-panels::page>
    <x-filament-widgets::widgets
        :columns="$this->getHeaderWidgetsColumns()"
        :data="$this->getWidgetData()"
        :widgets="$this->getVisibleHeaderWidgets()"
    />

    <x-filament-widgets::widgets
        :columns="$this->getFooterWidgetsColumns()"
        :data="$this->getWidgetData()"
        :widgets="$this->getVisibleFooterWidgets()"
    />

    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h2 class="text-base font-semibold text-gray-950 dark:text-white">How to read this</h2>
        <ul class="mt-3 list-inside list-disc space-y-1 text-sm text-gray-600 dark:text-gray-400">
            <li><strong>Invoiced</strong> — bills issued this calendar month (issue date).</li>
            <li><strong>Collected</strong> — cash/bKash/etc. marked completed with paid date this month.</li>
            <li><strong>Outstanding AR</strong> — remaining balance on invoices still open, partial, or draft.</li>
            <li><strong>Aging buckets</strong> — outstanding balance grouped by how long invoices have been past due.</li>
            <li><strong>ARPU</strong> — this month’s collections divided by active customers (simple proxy).</li>
        </ul>
    </div>
</x-filament-panels::page>

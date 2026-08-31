<x-enterprise-invoice
    :customer="$record->customer"
    :billing="$record->customer?->billing"
    :collections="$record->monthlyCollections ?? collect()"
    :invoice-no="(string) $record->id"
    :invoice-date="$record->summary_date ?? now()"
/>

<div class="text-center mt-3 no-print" x-data="{
    handlePrint() {
        window.print();
    }
}">
    <button type="button" class="btn btn-sm btn-primary" x-on:click="handlePrint()">{{ __('Print Invoice') }}</button>
</div>

<style>
    @media print {
        .fi-sidebar, .fi-topbar, .fi-header, .no-print {
            display: none !important;
        }
        .fi-main-ctn {
            margin-left: 0 !important;
            padding-top: 0 !important;
        }
        body { background-color: white !important; }
    }
</style>

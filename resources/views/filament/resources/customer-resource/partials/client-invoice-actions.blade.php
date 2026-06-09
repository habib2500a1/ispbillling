@props(['invoice'])

@php
    /** @var \App\Models\Invoice $invoice */
    $pdfUrl = route('invoices.pdf', $invoice);
@endphp

<div class="isp-cv-invoice-actions">
    <a href="{{ $pdfUrl }}" target="_blank" rel="noopener" class="isp-cv-invoice-actions__btn" title="View invoice">
        <x-filament::icon icon="heroicon-o-eye" class="h-3.5 w-3.5" />
        <span>View</span>
    </a>
    <a href="{{ $pdfUrl }}" class="isp-cv-invoice-actions__btn" title="Download PDF" download>
        <x-filament::icon icon="heroicon-o-arrow-down-tray" class="h-3.5 w-3.5" />
        <span>PDF</span>
    </a>
</div>

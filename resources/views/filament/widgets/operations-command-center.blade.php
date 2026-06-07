@php
    $primary = $ops['primary'] ?? [];
    $sections = $ops['sections'] ?? [];
    $feeds = $ops['feeds'] ?? [];
    $billingAside = $ops['billing_aside'] ?? null;
    $highlights = $ops['highlights'] ?? [];
    $mfsPending = $ops['mfs_pending_verify'] ?? ['count' => 0, 'url' => null, 'items' => []];
    $updated = $ops['updated_at'] ?? null;
@endphp

<x-filament-widgets::widget>
    <div class="isp-cmd-center isp-cmd-center--pro isp-cmd-center--v2">
        @include('filament.widgets.partials.ops-hero-primary', compact(
            'ops', 'primary', 'highlights', 'mfsPending', 'updated'
        ))

        @if ($sections !== [])
            @include('filament.widgets.partials.ops-sections', compact('sections'))
        @endif

        <div @class(['isp-cmd-body isp-cmd-body--v2', 'isp-cmd-body--has-aside' => is_array($billingAside)])>
            @include('filament.widgets.partials.ops-feeds-tabs', compact('feeds', 'mfsPending'))

            @if (is_array($billingAside))
                <div class="isp-cmd-aside-wrap" data-isp-dash-accordion>
                    <button
                        type="button"
                        class="isp-cmd-aside-wrap__toggle"
                        data-isp-dash-accordion-summary
                        aria-expanded="true"
                    >
                        <span>{{ $billingAside['title'] ?? 'Billing snapshot' }}</span>
                        <x-heroicon-m-chevron-down class="h-5 w-5" />
                    </button>
                    <div class="isp-cmd-aside-wrap__body" data-isp-dash-accordion-body>
                        @include('filament.widgets.partials.ops-billing-aside', ['aside' => $billingAside])
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-filament-widgets::widget>

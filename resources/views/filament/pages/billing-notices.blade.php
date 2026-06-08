@php
    $payload = $this->getNotices();
    $summary = $payload['summary'] ?? [];
    $sections = $payload['sections'] ?? [];
    $total = (int) ($summary['total'] ?? 0);
@endphp

{!! \App\Support\BillingStyles::navigatedScript() !!}

<x-filament-panels::page class="isp-billing-notices-page">
    <div class="bl-notices">
        <header class="bl-notices-hero">
            <h1 class="bl-notices-hero__title">Billing notices</h1>
            <p class="bl-notices-hero__sub">
                MFS verify pending · overdue bills · due within 3 days — act before subscribers go offline.
            </p>
        </header>

        <div class="bl-notices-summary">
            <div class="bl-notices-summary__item">
                <span class="bl-notices-summary__value">{{ number_format($summary['mfs_pending'] ?? 0) }}</span>
                <span class="bl-notices-summary__label">MFS pending</span>
            </div>
            <div class="bl-notices-summary__item">
                <span class="bl-notices-summary__value">{{ number_format($summary['overdue'] ?? 0) }}</span>
                <span class="bl-notices-summary__label">Overdue</span>
            </div>
            <div class="bl-notices-summary__item">
                <span class="bl-notices-summary__value">{{ number_format($summary['due_soon'] ?? 0) }}</span>
                <span class="bl-notices-summary__label">Due soon</span>
            </div>
            <div class="bl-notices-summary__item">
                <span class="bl-notices-summary__value">{{ number_format($total) }}</span>
                <span class="bl-notices-summary__label">Total actions</span>
            </div>
        </div>

        @if ($total === 0)
            <div class="bl-notices-empty">
                <div class="bl-notices-empty__icon">
                    <x-filament::icon icon="heroicon-o-check-circle" class="h-8 w-8" />
                </div>
                <p style="margin:0;font-size:1rem;font-weight:700;color:var(--bl-text);">All clear</p>
                <p style="margin:0.35rem 0 0;font-size:0.85rem;">No billing notices need attention right now.</p>
            </div>
        @else
            @foreach ($sections as $section)
                <section class="bl-notices-section">
                    <div class="bl-notices-section__head">
                        <div>
                            <h2 class="bl-notices-section__title">{{ $section['title'] ?? 'Notice' }}</h2>
                            @if (! empty($section['hint']))
                                <p class="bl-notices-section__hint">{{ $section['hint'] }}</p>
                            @endif
                        </div>
                        @if (! empty($section['url']))
                            <a href="{{ $section['url'] }}" class="bl-notices-section__link">View all →</a>
                        @endif
                    </div>
                    <div class="bl-notices-list">
                        @foreach ($section['items'] ?? [] as $item)
                            <a href="{{ $item['url'] ?? '#' }}" class="bl-notice-item">
                                <span @class([
                                    'bl-notice-item__dot',
                                    'bl-notice-item__dot--danger' => ($item['severity'] ?? '') === 'danger',
                                    'bl-notice-item__dot--warning' => ($item['severity'] ?? '') === 'warning',
                                    'bl-notice-item__dot--amber' => ($item['severity'] ?? '') === 'amber',
                                ])></span>
                                <div>
                                    <p class="bl-notice-item__title">{{ $item['title'] ?? '' }}</p>
                                    <p class="bl-notice-item__message">{{ $item['message'] ?? '' }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        @endif

        <div class="bh-quick-actions" style="margin-top:0.5rem;">
            <a href="{{ \App\Filament\Pages\BillingOverview::getUrl() }}" class="bh-quick-actions__btn">
                <x-filament::icon icon="heroicon-m-squares-2x2" class="h-4 w-4" />
                Billing center
            </a>
            <a href="{{ \App\Filament\Resources\InvoiceResource::getUrl('due') }}" class="bh-quick-actions__btn">
                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-4 w-4" />
                Due bills
            </a>
            <a href="{{ \App\Filament\Pages\BillCollectionDesk::getUrl() }}" class="bh-quick-actions__btn bh-quick-actions__btn--primary">
                <x-filament::icon icon="heroicon-m-currency-bangladeshi" class="h-4 w-4" />
                Collect payment
            </a>
        </div>
    </div>
</x-filament-panels::page>

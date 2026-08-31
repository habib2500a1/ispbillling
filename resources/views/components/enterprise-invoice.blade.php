@props([
    'customer',
    'billing' => null,
    'collections' => collect(),
    'invoiceNo' => '',
    'invoiceDate' => null,
    'documentLabel' => __('Invoice'),
    'currency' => null,
    'showToolbar' => false,
    'backUrl' => null,
    'downloadUrl' => null,
])

@php
    $currency = $currency ?: (siteUrlSettings('site_currency') ?: 'BDT');
    $invoiceDate = $invoiceDate ? \Carbon\Carbon::parse($invoiceDate) : now();
    $billing = $billing ?: $customer->billing;
    $address = $customer->customerAddress
        ->map(fn ($a) => trim(implode(', ', array_filter([
            $a->input_type_text ?? null,
            $a->input_type_dropdown ?? null,
            $a->input_type_textarea ?? null,
        ]))))
        ->filter()
        ->implode('; ');
    $monthlyRent = (float) ($billing->monthly_rent ?? 0);
    $additional = (float) ($billing->additional_charge ?? 0);
    $vat = (float) ($billing->vat ?? 0);
    $previousDue = (float) ($billing->previous_due ?? 0);
    $discount = (float) ($billing->discount ?? 0);
    $advance = (float) ($billing->advance ?? 0);
    $paidAmount = (float) ($billing->paid_amount ?? 0);
    $subtotal = $monthlyRent + $additional + $vat + $previousDue;
    $grandTotal = max(0, $subtotal - $discount - $advance);
    $dueAmount = (float) ($billing->due_amount ?? max(0, $grandTotal - $paidAmount));
    $billMonth = $billing->billing_month ?? null;
    $terms = siteUrlSettings('site_invoice_terms');
    $accent = siteUrlSettings('site_invoice_color') ?: '#1e3a5f';
    $onu = $customer->relationLoaded('onus') ? $customer->primaryOnu() : $customer->onus()->orderByDesc('last_polled_at')->orderByDesc('id')->first();
@endphp

<div class="inv-enterprise-wrap">
    @if ($showToolbar)
        <div class="inv-enterprise-toolbar no-print d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            @if ($backUrl)
                <a href="{{ $backUrl }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>{{ __('Back') }}
                </a>
            @else
                <span></span>
            @endif
            <div class="d-flex flex-wrap gap-2">
                @if ($downloadUrl)
                    <a href="{{ $downloadUrl }}" class="btn btn-sm btn-success">
                        <i class="bi bi-download me-1"></i>{{ __('Download / Print') }}
                    </a>
                @endif
                <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>{{ __('Print') }}
                </button>
            </div>
        </div>
    @endif

    <article class="inv-enterprise" id="print-section" style="--inv-accent: {{ $accent }};">
        <header class="inv-enterprise__header">
            <div class="inv-enterprise__brand">
                @if (siteUrlSettings('site_invoice_logo') || siteUrlSettings('site_logo'))
                    <img class="inv-enterprise__logo" src="{{ site_invoice_image() }}" alt="{{ site_brand() }}">
                @endif
                <div>
                    <div class="inv-enterprise__company">{{ siteUrlSettings('site_name') ?: site_brand() }}</div>
                    @if (siteUrlSettings('site_address'))
                        <div class="inv-enterprise__muted">{{ siteUrlSettings('site_address') }}</div>
                    @endif
                    <div class="inv-enterprise__muted">
                        {{ siteUrlSettings('site_phone') }}
                        @if (siteUrlSettings('site_email')) · {{ siteUrlSettings('site_email') }} @endif
                    </div>
                </div>
            </div>
            <div class="inv-enterprise__meta">
                <span class="inv-enterprise__badge">{{ strtoupper($documentLabel) }}</span>
                @if ($invoiceNo !== '')
                    <div class="inv-enterprise__number">#{{ $invoiceNo }}</div>
                @endif
                <div class="inv-enterprise__muted">{{ $invoiceDate->format('d M Y') }}</div>
                @if ($customer->status)
                    <span class="inv-enterprise__status">{{ ucfirst($customer->status) }}</span>
                @endif
            </div>
        </header>

        <section class="inv-enterprise__grid inv-enterprise__parties">
            <div class="inv-enterprise__card">
                <div class="inv-enterprise__label">{{ __('Bill to') }}</div>
                <div class="inv-enterprise__name">{{ $customer->customer_name }}</div>
                <div class="inv-enterprise__muted">{{ $customer->customer_unique_id }}</div>
                @if ($customer->pppUser?->username)
                    <div class="inv-enterprise__muted">PPPoE: {{ $customer->pppUser->username }}</div>
                @endif
                @if ($address !== '')
                    <div class="inv-enterprise__muted">{{ $address }}</div>
                @endif
                @if ($customer->mobile)
                    <div class="inv-enterprise__muted">{{ $customer->mobile }}</div>
                @endif
                @if ($onu)
                    <div class="inv-enterprise__muted mt-2">
                        <strong>{{ __('ONU / Fiber') }}:</strong>
                        @if ($onu->mac_address) MAC {{ $onu->mac_address }} @endif
                        @if ($onu->pon_port) · PON {{ $onu->pon_port }} @endif
                        @if ($onu->olt_name) · OLT {{ $onu->olt_name }} @endif
                        @if ($onu->serial_number) · SN {{ $onu->serial_number }} @endif
                    </div>
                @endif
            </div>
            <div class="inv-enterprise__card">
                <div class="inv-enterprise__label">{{ __('Pay to') }}</div>
                <div class="inv-enterprise__name">{{ siteUrlSettings('site_title') ?: site_brand() }}</div>
                <div class="inv-enterprise__muted">{{ siteUrlSettings('site_address') }}</div>
                <div class="inv-enterprise__muted">{{ siteUrlSettings('site_phone') }}</div>
                @if ($billing?->auto_disable_date)
                    <div class="inv-enterprise__due-chip">
                        {{ __('Expire') }}: {{ \Carbon\Carbon::parse($billing->auto_disable_date)->format('d M Y') }}
                    </div>
                @endif
            </div>
        </section>

        <section class="inv-enterprise__details d-none d-md-block">
            <table class="table table-sm inv-enterprise__table mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Description') }}</th>
                        <th>{{ __('Period') }}</th>
                        <th class="text-end">{{ __('Amount') }} ({{ $currency }})</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong>{{ __('Internet service') }}</strong>
                            @if ($customer->package?->package)
                                <div class="inv-enterprise__muted">{{ $customer->package->package }}</div>
                            @endif
                            <div class="inv-enterprise__muted small">
                                {{ __('Billing type') }}: {{ $billing->billing_type ?? '—' }}
                                @if ($customer->connection_date)
                                    · {{ __('Connected') }} {{ \Carbon\Carbon::parse($customer->connection_date)->format('d M Y') }}
                                @endif
                                @if ($onu?->mac_address)
                                    · ONU {{ $onu->mac_address }}
                                @endif
                            </div>
                        </td>
                        <td>
                            @if ($billMonth)
                                {{ \Carbon\Carbon::parse($billMonth)->format('M Y') }}
                            @else
                                {{ $invoiceDate->format('M Y') }}
                            @endif
                        </td>
                        <td class="text-end fw-semibold">{{ number_format($monthlyRent, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="inv-enterprise__details-mobile d-md-none">
            <div class="inv-enterprise__card">
                <div class="inv-enterprise__label">{{ __('Internet service') }}</div>
                @if ($customer->package?->package)
                    <div>{{ $customer->package->package }}</div>
                @endif
                @if ($onu)
                    <div class="inv-enterprise__muted small">
                        ONU: {{ $onu->mac_address ?: '—' }}
                        @if ($onu->pon_port) · {{ $onu->pon_port }} @endif
                    </div>
                @endif
                <div class="inv-enterprise__row">
                    <span>{{ __('Monthly rent') }}</span>
                    <strong>{{ number_format($monthlyRent, 2) }} {{ $currency }}</strong>
                </div>
            </div>
        </section>

        <section class="inv-enterprise__grid inv-enterprise__summary">
            <div class="inv-enterprise__card">
                <div class="inv-enterprise__label">{{ __('Payment history') }}</div>
                @forelse ($collections as $summary)
                    <div class="inv-enterprise__row">
                        <span>{{ \Carbon\Carbon::parse($summary->collection_date)->format('d M Y') }}</span>
                        <strong>{{ number_format((float) $summary->collection_amount, 2) }} {{ $currency }}</strong>
                    </div>
                @empty
                    <div class="inv-enterprise__muted">{{ __('No payments recorded this cycle.') }}</div>
                @endforelse
            </div>
            <div class="inv-enterprise__totals">
                <div class="inv-enterprise__row"><span>{{ __('Monthly rent') }}</span><span>{{ number_format($monthlyRent, 2) }}</span></div>
                @if ($additional > 0)
                    <div class="inv-enterprise__row"><span>{{ __('Additional') }}</span><span>{{ number_format($additional, 2) }}</span></div>
                @endif
                @if ($vat > 0)
                    <div class="inv-enterprise__row"><span>{{ __('VAT') }}</span><span>{{ number_format($vat, 2) }}</span></div>
                @endif
                @if ($previousDue > 0)
                    <div class="inv-enterprise__row"><span>{{ __('Previous due') }}</span><span>{{ number_format($previousDue, 2) }}</span></div>
                @endif
                @if ($discount > 0)
                    <div class="inv-enterprise__row text-success"><span>{{ __('Discount') }}</span><span>-{{ number_format($discount, 2) }}</span></div>
                @endif
                @if ($advance > 0)
                    <div class="inv-enterprise__row text-success"><span>{{ __('Advance') }}</span><span>-{{ number_format($advance, 2) }}</span></div>
                @endif
                <div class="inv-enterprise__row inv-enterprise__row--total">
                    <span>{{ __('Grand total') }}</span>
                    <strong>{{ number_format($grandTotal, 2) }} {{ $currency }}</strong>
                </div>
                <div class="inv-enterprise__row"><span>{{ __('Paid') }}</span><span>{{ number_format($paidAmount, 2) }}</span></div>
                <div class="inv-enterprise__row inv-enterprise__row--due">
                    <span>{{ __('Due') }}</span>
                    <strong>{{ number_format($dueAmount, 2) }} {{ $currency }}</strong>
                </div>
            </div>
        </section>

        @if ($terms)
            <section class="inv-enterprise__terms">
                <div class="inv-enterprise__label">{{ __('Terms & Conditions') }}</div>
                <div class="inv-enterprise__terms-body">{!! $terms !!}</div>
            </section>
        @else
            <section class="inv-enterprise__terms inv-enterprise__muted small">
                {{ siteUrlSettings('site_invoice_footer') ?: __('This is a computer generated invoice. No signature required.') }}
            </section>
        @endif

        @if (siteUrlSettings('site_invoice_signature'))
            <div class="inv-enterprise__signature text-end">
                <img src="{{ site_image(siteUrlSettings('site_invoice_signature')) }}" alt="{{ __('Signature') }}" class="inv-enterprise__sig-img">
                <div class="inv-enterprise__muted small">{{ __('Authorized signature') }}</div>
            </div>
        @endif
    </article>
</div>

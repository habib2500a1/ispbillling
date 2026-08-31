<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Invoice') }} #{{ $invoiceNo }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --inv-accent: {{ siteUrlSettings('site_invoice_color') ?: '#1e3a5f' }}; }
        body { background: #f1f5f9; color: #0f172a; margin: 0; }
        .inv-enterprise-wrap { max-width: 920px; margin: 1rem auto; padding: 0 .75rem 1.5rem; width: 100%; box-sizing: border-box; }
        .inv-enterprise { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 12px 40px rgba(15,23,42,.08); }
        .inv-enterprise__header { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem; padding: 1.25rem 1.5rem; background: linear-gradient(135deg, var(--inv-accent), #06ad73); color: #fff; }
        .inv-enterprise__brand { display: flex; gap: .875rem; align-items: flex-start; min-width: 0; flex: 1 1 220px; }
        .inv-enterprise__logo { max-height: 46px; max-width: 150px; object-fit: contain; background: #fff; border-radius: 8px; padding: 4px 8px; }
        .inv-enterprise__company { font-weight: 700; font-size: 1.05rem; }
        .inv-enterprise__meta { text-align: right; }
        .inv-enterprise__badge { display: inline-block; font-size: .68rem; letter-spacing: .12em; opacity: .85; }
        .inv-enterprise__number { font-family: ui-monospace, monospace; font-size: 1.35rem; font-weight: 700; }
        .inv-enterprise__status { display: inline-block; margin-top: .35rem; padding: .15rem .55rem; border-radius: 999px; background: rgba(255,255,255,.18); font-size: .72rem; text-transform: capitalize; }
        .inv-enterprise__muted { color: #64748b; font-size: .86rem; line-height: 1.45; }
        .inv-enterprise__header .inv-enterprise__muted { color: rgba(255,255,255,.82); }
        .inv-enterprise__grid { display: grid; gap: 1rem; padding: 1rem 1.25rem; }
        @media (min-width: 768px) { .inv-enterprise__grid { grid-template-columns: 1fr 1fr; padding: 1.25rem 1.5rem; } }
        .inv-enterprise__parties { border-bottom: 1px solid #eef2f7; }
        .inv-enterprise__card { border: 1px solid #e8eef5; border-radius: 12px; padding: .875rem 1rem; min-width: 0; word-break: break-word; }
        .inv-enterprise__label { font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; color: #64748b; font-weight: 700; margin-bottom: .35rem; }
        .inv-enterprise__name { font-weight: 700; font-size: 1rem; margin-bottom: .15rem; }
        .inv-enterprise__row { display: flex; justify-content: space-between; gap: .75rem; padding: .35rem 0; font-size: .9rem; }
        .inv-enterprise__summary { align-items: start; background: #f8fafc; border-top: 1px solid #eef2f7; }
        .inv-enterprise__totals { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: .875rem 1rem; }
        .inv-enterprise__row--total { border-top: 1px dashed #cbd5e1; margin-top: .35rem; padding-top: .55rem; }
        .inv-enterprise__row--due { color: var(--inv-accent); font-size: 1rem; font-weight: 700; }
        .inv-enterprise__amount-hero { margin: 0 1.25rem 1rem; padding: 1rem; border-radius: 12px; background: #ecfdf5; border: 1px solid #bbf7d0; text-align: center; }
        .inv-enterprise__amount-hero strong { display: block; font-size: 1.5rem; color: #047857; }
        .inv-enterprise__terms { padding: .75rem 1.25rem 1.25rem; border-top: 1px solid #eef2f7; font-size: .84rem; color: #475569; }
        .inv-enterprise-toolbar { gap: .5rem; }
        @media print { body { background: #fff; } .no-print { display: none !important; } .inv-enterprise-wrap { margin: 0; max-width: none; padding: 0; } .inv-enterprise { box-shadow: none; border: none; } }
    </style>
</head>
<body>
    @php
        $received = (float) $collection->collection_amount;
    @endphp

    <div class="inv-enterprise-wrap">
        <div class="inv-enterprise-toolbar no-print d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('customers.show', encrypt($customer->customer_unique_id)) }}"
               class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>{{ __('Back') }}
            </a>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('collection-invoice.download', $collection->id) }}" class="btn btn-sm btn-success">
                    <i class="bi bi-download me-1"></i>{{ __('Download / Print') }}
                </a>
                <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>{{ __('Print') }}
                </button>
            </div>
        </div>

        <article class="inv-enterprise" id="print-section">
            <header class="inv-enterprise__header">
                <div class="inv-enterprise__brand">
                    @if (siteUrlSettings('site_invoice_logo') || siteUrlSettings('site_logo'))
                        <img class="inv-enterprise__logo" src="{{ site_invoice_image() }}" alt="">
                    @endif
                    <div>
                        <div class="inv-enterprise__company">{{ siteUrlSettings('site_name') ?: site_brand() }}</div>
                        <div class="inv-enterprise__muted">{{ siteUrlSettings('site_address') }}</div>
                        <div class="inv-enterprise__muted">{{ siteUrlSettings('site_phone') }} @if(siteUrlSettings('site_email')) · {{ siteUrlSettings('site_email') }} @endif</div>
                    </div>
                </div>
                <div class="inv-enterprise__meta">
                    <span class="inv-enterprise__badge">{{ __('Money receipt') }}</span>
                    <div class="inv-enterprise__number">#{{ $invoiceNo }}</div>
                    <div class="inv-enterprise__muted">{{ $collection->collection_date ? \Carbon\Carbon::parse($collection->collection_date)->format('d M Y h:i A') : now()->format('d M Y') }}</div>
                    <span class="inv-enterprise__status">{{ strtoupper($collection->payment_status ?: 'paid') }}</span>
                </div>
            </header>

            <section class="inv-enterprise__grid inv-enterprise__parties">
                <div class="inv-enterprise__card">
                    <div class="inv-enterprise__label">{{ __('Bill to') }}</div>
                    <div class="inv-enterprise__name">{{ $customer->customer_name }}</div>
                    <div class="inv-enterprise__muted">{{ $customer->customer_unique_id }} @if($customer->pppUser?->username) · PPPoE {{ $customer->pppUser->username }} @endif</div>
                    @if($address)<div class="inv-enterprise__muted">{{ $address }}</div>@endif
                    <div class="inv-enterprise__muted">{{ $customer->mobile }}</div>
                </div>
                <div class="inv-enterprise__card">
                    <div class="inv-enterprise__label">{{ __('Collected by') }}</div>
                    <div class="inv-enterprise__name">{{ $collection->collected_by ?: '—' }}</div>
                    <div class="inv-enterprise__muted">{{ $collection->bill_month ?: \Carbon\Carbon::parse($collection->collection_date)->format('F Y') }}</div>
                    @if($billing?->auto_disable_date)
                        <div class="inv-enterprise__muted mt-2">{{ __('Expire') }}: {{ \Carbon\Carbon::parse($billing->auto_disable_date)->format('d M Y') }}</div>
                    @endif
                </div>
            </section>

            <div class="inv-enterprise__amount-hero">
                <div class="inv-enterprise__label mb-1">{{ __('Amount received') }}</div>
                <strong>{{ number_format($received, 2) }} {{ $currency }}</strong>
            </div>

            <section class="inv-enterprise__grid inv-enterprise__summary">
                <div class="inv-enterprise__card">
                    <div class="inv-enterprise__label">{{ __('Service') }}</div>
                    <div>{{ __('Internet bill collection') }}</div>
                    @if($customer->package?->package)
                        <div class="inv-enterprise__muted">{{ $customer->package->package }}</div>
                    @endif
                    @php $onu = $customer->primaryOnu(); @endphp
                    @if($onu)
                        <div class="inv-enterprise__muted small">
                            {{ __('ONU') }}: {{ $onu->mac_address ?: '—' }}
                            @if($onu->pon_port) · PON {{ $onu->pon_port }} @endif
                            @if($onu->olt_name) · OLT {{ $onu->olt_name }} @endif
                        </div>
                    @endif
                </div>
                <div class="inv-enterprise__totals">
                    <div class="inv-enterprise__row"><span>{{ __('Monthly rent') }}</span><span>{{ number_format((float) ($billing?->monthly_rent ?? 0), 2) }}</span></div>
                    <div class="inv-enterprise__row"><span>{{ __('Paid (cycle)') }}</span><span>{{ number_format((float) ($billing?->paid_amount ?? 0), 2) }}</span></div>
                    <div class="inv-enterprise__row inv-enterprise__row--due"><span>{{ __('Due') }}</span><strong>{{ number_format(max(0, (float) ($billing?->due_amount ?? 0)), 2) }} {{ $currency }}</strong></div>
                </div>
            </section>

            <section class="inv-enterprise__terms text-center">
                {{ siteUrlSettings('site_invoice_footer') ?: __('This is a computer generated receipt. No signature required.') }}
            </section>
        </article>
    </div>

    @if($autoPrint)
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
    @endif
</body>
</html>

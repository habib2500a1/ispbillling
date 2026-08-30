<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Invoice') }} #{{ $invoiceNo }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f4f6f8; color: #1e3a5f; }
        .receipt-wrap { max-width: 820px; margin: 1.5rem auto; }
        .receipt {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(30, 58, 95, .08);
        }
        .receipt-head {
            background: linear-gradient(135deg, #1e3a5f, #06ad73);
            color: #fff;
            padding: 1.25rem 1.5rem;
        }
        .inv-no { font-family: ui-monospace, monospace; letter-spacing: .04em; }
        .muted { color: #64748b; }
        .toolbar { gap: .5rem; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .receipt-wrap { margin: 0; max-width: none; }
            .receipt { box-shadow: none; border-radius: 0; }
        }
    </style>
</head>
<body>
    <div class="receipt-wrap">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 no-print toolbar">
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

        <div class="receipt" id="print-section">
            <div class="receipt-head d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    @if(siteUrlSettings('site_logo'))
                        <img src="{{ site_image(siteUrlSettings('site_logo')) }}" alt="" style="max-height:42px;background:#fff;border-radius:6px;padding:4px 8px;">
                    @endif
                    <div class="fw-bold fs-5 mt-2">{{ siteUrlSettings('site_name') ?: site_brand() }}</div>
                    <div class="small opacity-75">{{ siteUrlSettings('site_address') }}</div>
                    <div class="small opacity-75">{{ siteUrlSettings('site_phone') }} {{ siteUrlSettings('site_email') }}</div>
                </div>
                <div class="text-end">
                    <div class="text-uppercase small opacity-75">{{ __('Money receipt') }}</div>
                    <div class="fs-4 fw-bold inv-no">#{{ $invoiceNo }}</div>
                    <div class="small">{{ $collection->collection_date ? \Carbon\Carbon::parse($collection->collection_date)->format('d M Y h:i A') : now()->format('d M Y') }}</div>
                    <span class="badge bg-light text-success mt-1">{{ strtoupper($collection->payment_status ?: 'paid') }}</span>
                </div>
            </div>

            <div class="p-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="small text-muted">{{ __('Bill to') }}</div>
                        <div class="fw-bold">{{ $customer->customer_name }}</div>
                        <div class="small">{{ $customer->customer_unique_id }}
                            @if($customer->pppUser?->username) · PPPoE {{ $customer->pppUser->username }} @endif
                        </div>
                        @if($address)<div class="small muted">{{ $address }}</div>@endif
                        <div class="small">{{ $customer->mobile }}</div>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div class="small text-muted">{{ __('Collected by') }}</div>
                        <div class="fw-semibold">{{ $collection->collected_by ?: '—' }}</div>
                        <div class="small muted">{{ $collection->bill_month ?: \Carbon\Carbon::parse($collection->collection_date)->format('F Y') }}</div>
                    </div>
                </div>

                <table class="table table-sm align-middle mb-3">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Description') }}</th>
                            <th class="text-end">{{ __('Amount') }} ({{ $currency }})</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                {{ __('Internet bill collection') }}
                                @if($customer->package?->package)<div class="small text-muted">{{ $customer->package->package }}</div>@endif
                            </td>
                            <td class="text-end fw-semibold">{{ number_format((float) $collection->collection_amount, 2) }}</td>
                        </tr>
                    </tbody>
                </table>

                <div class="row">
                    <div class="col-md-7 small muted">
                        <div class="fw-semibold text-dark mb-1">{{ __('Billing snapshot') }}</div>
                        <div>{{ __('Monthly rent') }}: {{ number_format((float) ($billing?->monthly_rent ?? 0), 2) }}</div>
                        <div>{{ __('Paid (cycle)') }}: {{ number_format((float) ($billing?->paid_amount ?? 0), 2) }}</div>
                        <div>{{ __('Due') }}: {{ number_format(max(0, (float) ($billing?->due_amount ?? 0)), 2) }}</div>
                    </div>
                    <div class="col-md-5">
                        <div class="border rounded-3 p-3 bg-light">
                            <div class="d-flex justify-content-between"><span>{{ __('Received') }}</span><strong>{{ number_format((float) $collection->collection_amount, 2) }} {{ $currency }}</strong></div>
                        </div>
                    </div>
                </div>

                <div class="text-center small muted mt-4">
                    {{ __('This is a computer generated receipt. No signature required.') }}
                </div>
            </div>
        </div>
    </div>

    @if($autoPrint)
        <script>
            window.addEventListener('load', function () {
                setTimeout(function () { window.print(); }, 250);
            });
        </script>
    @endif
</body>
</html>

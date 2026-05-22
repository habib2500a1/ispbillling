@extends('bill-payment.layout', ['companyName' => $companyName])

@section('title', 'Your invoice')

@section('content')
    @php
        $customer = $summary['customer'];
        $invoices = $summary['invoices'];
    @endphp
    <div class="bp-card bp-card-wide">
        <h2 class="bp-title">Hello, {{ $customer->name }}</h2>
        <p class="bp-sub">
            Client code: <strong>{{ $customer->customer_code }}</strong>
            · {{ $summary['status_label'] }}
            @if ($summary['package_name'])
                · {{ $summary['package_name'] }}
            @endif
        </p>

        @if (session('status'))
            <div class="bp-alert bp-alert-ok">{{ session('status') }}</div>
        @endif
        @if (session('danger'))
            <div class="bp-alert bp-alert-err">{{ session('danger') }}</div>
        @endif
        @if (session('payment_link_url'))
            <div class="bp-alert bp-alert-ok">
                <p class="font-semibold">Payment link (copy &amp; share)</p>
                <input type="text" readonly value="{{ session('payment_link_url') }}" class="bp-input mt-2 text-xs" onclick="this.select()">
            </div>
        @endif

        <div class="bp-summary-grid">
            <div class="bp-stat">
                <span class="text-xs uppercase text-slate-500">Total due</span>
                <strong>{{ number_format($summary['total_due'], 2) }} BDT</strong>
            </div>
            <div class="bp-stat">
                <span class="text-xs uppercase text-slate-500">Wallet balance</span>
                <strong>{{ number_format($summary['wallet_balance'], 2) }} BDT</strong>
            </div>
        </div>

        @if ($summary['total_due'] > 0)
            <div class="bp-alert bp-alert-err mt-4">
                Pay each invoice in <strong>full</strong> (partial / manual amount is not allowed).
                Your internet line turns on automatically only after <strong>all dues are cleared</strong>.
            </div>
        @else
            <div class="bp-alert bp-alert-ok mt-4">No due invoices. Wallet top-up is available for advance credit.</div>
        @endif

        <nav class="bp-tabs mt-6">
            <a href="{{ route('bill-payment.invoice', ['tab' => 'invoices']) }}" class="bp-tab {{ $activeTab === 'invoices' ? 'bp-tab-active' : '' }}">Pay bill</a>
            @if ($walletTopupEnabled)
                <a href="{{ route('bill-payment.invoice', ['tab' => 'wallet']) }}" class="bp-tab {{ $activeTab === 'wallet' ? 'bp-tab-active' : '' }}">Wallet top-up</a>
            @endif
            <a href="{{ route('bill-payment.invoice', ['tab' => 'link']) }}" class="bp-tab {{ $activeTab === 'link' ? 'bp-tab-active' : '' }}">Payment link</a>
        </nav>

        @if ($activeTab === 'invoices')
            @if ($invoices->isEmpty())
                <div class="bp-alert bp-alert-ok mt-4">No outstanding invoice. You can add advance to wallet below.</div>
            @else
                <h3 class="mt-4 text-sm font-bold uppercase tracking-wide text-slate-500">Outstanding invoices</h3>
                @foreach ($invoices as $invoice)
                    @php
                        $due = $invoice->balanceDue();
                        $defaultAmount = $linkInvoiceId == $invoice->id && $linkAmount ? min($linkAmount, $due) : $due;
                    @endphp
                    <div class="bp-invoice-row">
                        <div class="flex-1">
                            <p class="font-semibold text-slate-900">{{ $invoice->invoice_number }}</p>
                            <p class="text-sm text-slate-500">
                                Due {{ $invoice->due_date?->format('d M Y') }}
                                @if ($invoice->isOverdue())
                                    <span class="text-rose-600">(overdue)</span>
                                @endif
                            </p>
                            <p class="text-lg font-bold text-teal-700">{{ number_format($due, 2) }} BDT due</p>
                            <a href="{{ route('bill-payment.invoice.pdf', $invoice) }}" class="mt-1 inline-block text-xs font-semibold text-teal-700 underline">Download PDF</a>
                        </div>
                        @if (($anyGatewayEnabled ?? false) && $summary['can_pay'])
                            <form method="post" action="{{ route('bill-payment.pay', $invoice) }}" class="bp-pay-form">
                                @csrf
                                <p class="text-sm font-semibold text-teal-800">Pay full due: {{ number_format($due, 2) }} BDT</p>
                                <div class="mt-3">
                                    @include('bill-payment.partials.payment-methods', ['methods' => $paymentMethods ?? []])
                                </div>
                            </form>
                        @elseif (! ($anyGatewayEnabled ?? false))
                            <p class="text-sm text-amber-700">Online payment unavailable.</p>
                        @endif
                    </div>
                @endforeach
            @endif
        @endif

        @if ($activeTab === 'wallet' && $walletTopupEnabled)
            <div class="mt-4 rounded-xl border border-teal-200 bg-teal-50/50 p-4">
                <h3 class="font-semibold text-teal-900">Advance / wallet top-up</h3>
                <p class="mt-1 text-sm text-slate-600">
                    Add money to your wallet in any amount. It is applied to bills automatically.
                    @if ($summary['total_due'] > 0)
                        <strong class="text-rose-700">Line stays off until all invoice dues above are paid in full.</strong>
                    @endif
                </p>
                @if (count($paymentMethods ?? []) > 0)
                    <form method="post" action="{{ route('bill-payment.wallet') }}" class="mt-4">
                        @csrf
                        <label class="text-sm font-medium text-slate-700">Amount (BDT)</label>
                        <input
                            type="number"
                            name="amount"
                            class="bp-input"
                            step="0.01"
                            min="{{ $walletMin }}"
                            value="{{ $linkAmount && $activeTab === 'wallet' ? number_format($linkAmount, 2, '.', '') : $walletMin }}"
                            required
                        >
                        <p class="mt-1 text-xs text-slate-500">Minimum {{ number_format($walletMin, 0) }} BDT</p>
                        <div class="mt-3">
                            @include('bill-payment.partials.payment-methods', ['methods' => $paymentMethods ?? []])
                        </div>
                    </form>
                @else
                    <p class="mt-2 text-sm text-amber-700">Online top-up is not enabled.</p>
                @endif
            </div>
        @endif

        @if ($activeTab === 'link')
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <h3 class="font-semibold text-slate-900">Share payment link</h3>
                <p class="mt-1 text-sm text-slate-600">
                    Create a link to pay without entering client code again. Send via SMS or WhatsApp.
                </p>
                <form method="post" action="{{ route('bill-payment.payment-link.create') }}" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label class="text-sm font-medium">Link type</label>
                        <select name="purpose" class="bp-input">
                            <option value="invoice">Pay bill (choose invoice on open)</option>
                            <option value="wallet">Wallet top-up only</option>
                        </select>
                    </div>
                    @if ($invoices->isNotEmpty())
                        <div>
                            <label class="text-sm font-medium">Invoice (optional)</label>
                            <select name="invoice_id" class="bp-input">
                                <option value="">Any / customer chooses</option>
                                @foreach ($invoices as $inv)
                                    <option value="{{ $inv->id }}">{{ $inv->invoice_number }} — {{ number_format($inv->balanceDue(), 2) }} BDT</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label class="text-sm font-medium">Fixed amount (optional)</label>
                        <input type="number" name="amount" class="bp-input" step="0.01" min="10" placeholder="Leave empty for full due">
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="send_sms" value="1" checked class="rounded">
                        Send link via SMS to {{ $customer->phone }}
                    </label>
                    <button type="submit" class="bp-btn">Create payment link</button>
                </form>

                @if ($recentLinks->isNotEmpty())
                    <h4 class="mt-6 text-xs font-bold uppercase text-slate-500">Active links</h4>
                    @foreach ($recentLinks as $plink)
                        <div class="mt-2 rounded-lg border border-slate-200 bg-white p-3 text-sm">
                            <input type="text" readonly value="{{ $plink->publicUrl() }}" class="bp-input text-xs" onclick="this.select()">
                            <p class="mt-1 text-slate-500">Expires {{ $plink->expires_at->format('d M Y') }}
                                @if ($plink->amount) · {{ number_format((float) $plink->amount, 2) }} BDT @endif
                            </p>
                            @if ($customer->phone)
                                <div class="mt-2 flex flex-wrap gap-3 text-xs">
                                    <form method="post" action="{{ route('bill-payment.payment-link.sms', $plink) }}">
                                        @csrf
                                        <button type="submit" class="text-teal-700 underline">Resend SMS</button>
                                    </form>
                                    @php $wa = app(\App\Services\BillPayment\PaymentLinkService::class)->whatsAppShareUrl($plink, $customer); @endphp
                                    @if ($wa)
                                        <a href="{{ $wa }}" target="_blank" rel="noopener" class="font-semibold text-emerald-700 underline">WhatsApp</a>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                @endif
            </div>
        @endif

        <div class="mt-6 flex flex-wrap gap-4 text-sm">
            <a href="{{ route('portal.login') }}" class="bp-link">Customer portal</a>
            <form method="post" action="{{ route('bill-payment.reset') }}">
                @csrf
                <button type="submit" class="bp-link bg-transparent border-0 p-0 cursor-pointer">Different client code</button>
            </form>
        </div>
    </div>
@endsection

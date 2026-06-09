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
                <p class="bp-strong">Payment link (copy &amp; share)</p>
                <input type="text" readonly value="{{ session('payment_link_url') }}" class="bp-input bp-mt-2 bp-input-sm" onclick="this.select()">
            </div>
        @endif

        <div class="bp-summary-grid">
            <div class="bp-stat">
                <span class="bp-stat-label">Total due</span>
                <strong>{{ number_format($summary['total_due'], 2) }} BDT</strong>
            </div>
            <div class="bp-stat">
                <span class="bp-stat-label">Wallet balance</span>
                <strong>{{ number_format($summary['wallet_balance'], 2) }} BDT</strong>
            </div>
        </div>

        @if ($summary['total_due'] > 0)
            <div class="bp-alert bp-alert-err bp-mt-4">
                Pay each invoice in <strong>full</strong> (partial / manual amount is not allowed).
                Your internet line turns on automatically only after <strong>all dues are cleared</strong>.
            </div>
        @else
            <div class="bp-alert bp-alert-ok bp-mt-4">
                No due invoices right now.
                @if ($prepayEnabled ?? false)
                    @if ($prepayQuote ?? null)
                        Use the <a href="{{ route('bill-payment.invoice', ['tab' => 'prepay']) }}" class="bp-link-inline">Pay months</a> tab to pay {{ number_format((float) ($prepayQuote['monthly_rate'] ?? 0), 2) }} BDT per month in advance (1, 2, 12 months, etc.).
                    @else
                        Open the <a href="{{ route('bill-payment.invoice', ['tab' => 'prepay']) }}" class="bp-link-inline">Pay months</a> tab if advance payment is configured for your account.
                    @endif
                @elseif ($walletTopupEnabled)
                    Wallet top-up is available for advance credit.
                @endif
            </div>
        @endif

        <nav class="bp-tabs bp-mt-6">
            <a href="{{ route('bill-payment.invoice', ['tab' => 'invoices']) }}" class="bp-tab {{ $activeTab === 'invoices' ? 'bp-tab-active' : '' }}">Pay bill</a>
            @if ($prepayEnabled ?? false)
                <a href="{{ route('bill-payment.invoice', ['tab' => 'prepay']) }}" class="bp-tab {{ $activeTab === 'prepay' ? 'bp-tab-active' : '' }}">Pay months</a>
            @endif
            @if ($walletTopupEnabled)
                <a href="{{ route('bill-payment.invoice', ['tab' => 'wallet']) }}" class="bp-tab {{ $activeTab === 'wallet' ? 'bp-tab-active' : '' }}">Wallet top-up</a>
            @endif
            <a href="{{ route('bill-payment.invoice', ['tab' => 'link']) }}" class="bp-tab {{ $activeTab === 'link' ? 'bp-tab-active' : '' }}">Payment link</a>
        </nav>

        @if ($activeTab === 'invoices')
            @if ($invoices->isEmpty() && ($prepayEnabled ?? false) && ($prepayQuote ?? null))
                <div class="bp-mt-4">
                    <p class="bp-mb-3 bp-text-sm bp-strong">No bill due — pay advance months below or open the <a href="{{ route('bill-payment.invoice', ['tab' => 'prepay']) }}" class="bp-link-inline">Pay months</a> tab.</p>
                    <x-customer-prepay-form
                        :quote="$prepayQuote"
                        :action="route('bill-payment.prepay')"
                        :payment-methods="$paymentMethods ?? []"
                        :max-months="$prepayMaxMonths"
                        :quick-months="$prepayQuickMonths"
                        variant="bill-pay"
                    />
                </div>
            @elseif ($invoices->isEmpty())
                <div class="bp-alert bp-alert-ok bp-mt-4">
                    No outstanding invoice.
                    @if ($prepayEnabled ?? false)
                        Open the <a href="{{ route('bill-payment.invoice', ['tab' => 'prepay']) }}" class="bp-link-inline">Pay months</a> tab to extend your service in advance.
                    @elseif ($walletTopupEnabled)
                        You can add advance to wallet on the Wallet top-up tab.
                    @endif
                </div>
            @else
                <h3 class="bp-section-title">Outstanding invoices</h3>
                @foreach ($invoices as $invoice)
                    @php
                        $due = $invoice->balanceDue();
                        $defaultAmount = $linkInvoiceId == $invoice->id && $linkAmount ? min($linkAmount, $due) : $due;
                    @endphp
                    <div class="bp-invoice-row">
                        <div>
                            <p class="bp-inv-title">{{ $invoice->invoice_number }}</p>
                            <p class="bp-inv-meta">
                                Due {{ $invoice->due_date?->format('d M Y') }}
                                @if ($invoice->isOverdue())
                                    <span class="bp-overdue">(overdue)</span>
                                @endif
                            </p>
                            <p class="bp-inv-amount">{{ number_format($due, 2) }} BDT due</p>
                            <a href="{{ route('bill-payment.invoice.pdf', $invoice) }}" class="bp-mt-1 bp-text-xs bp-link-inline">Download PDF</a>
                        </div>
                        @if (($anyGatewayEnabled ?? false) && $summary['can_pay'])
                            <form method="post" action="{{ route('bill-payment.pay', $invoice) }}" class="bp-pay-form">
                                @csrf
                                <p class="bp-text-sm bp-strong">Pay full due: {{ number_format($due, 2) }} BDT</p>
                                <div class="bp-mt-3">
                                    @include('bill-payment.partials.payment-methods', ['methods' => $paymentMethods ?? []])
                                </div>
                            </form>
                        @elseif (! ($anyGatewayEnabled ?? false))
                            <p class="bp-warn">Online payment unavailable.</p>
                        @endif
                    </div>
                @endforeach
            @endif
        @endif

        @if ($activeTab === 'prepay')
            @if ($prepayQuote ?? null)
                <div class="bp-mt-4">
                    <x-customer-prepay-form
                        :quote="$prepayQuote"
                        :action="route('bill-payment.prepay')"
                        :payment-methods="$paymentMethods ?? []"
                        :max-months="$prepayMaxMonths"
                        :quick-months="$prepayQuickMonths"
                        variant="bill-pay"
                    />
                </div>
            @else
                <div class="bp-alert bp-alert-err bp-mt-4">
                    Advance month payment is not available for this account (no monthly package rate found).
                    Contact your ISP if you need to pay multiple months in advance.
                </div>
            @endif
        @endif

        @if ($activeTab === 'wallet' && $walletTopupEnabled)
            <div class="bp-panel">
                <h3 class="bp-panel-title">Advance / wallet top-up</h3>
                <p class="bp-panel-lead">
                    Add money to your wallet in any amount. It is applied to bills automatically.
                    @if ($summary['total_due'] > 0)
                        <strong>Line stays off until all invoice dues above are paid in full.</strong>
                    @endif
                </p>
                @if (count($paymentMethods ?? []) > 0)
                    <form method="post" action="{{ route('bill-payment.wallet') }}" class="bp-mt-4">
                        @csrf
                        <label class="bp-label">Amount (BDT)</label>
                        <input
                            type="number"
                            name="amount"
                            class="bp-input"
                            step="0.01"
                            min="{{ $walletMin }}"
                            value="{{ $linkAmount && $activeTab === 'wallet' ? number_format($linkAmount, 2, '.', '') : $walletMin }}"
                            required
                        >
                        <p class="bp-mt-1 bp-text-xs bp-hint-muted">Minimum {{ number_format($walletMin, 0) }} BDT</p>
                        <div class="bp-mt-3">
                            @include('bill-payment.partials.payment-methods', ['methods' => $paymentMethods ?? []])
                        </div>
                    </form>
                @else
                    <p class="bp-mt-2 bp-warn">Online top-up is not enabled.</p>
                @endif
            </div>
        @endif

        @if ($activeTab === 'link')
            <div class="bp-panel">
                <h3 class="bp-panel-title">Share payment link</h3>
                <p class="bp-panel-lead">
                    Create a link to pay without entering client code again. Send via SMS or WhatsApp.
                </p>
                <form method="post" action="{{ route('bill-payment.payment-link.create') }}" class="bp-mt-4 bp-stack">
                    @csrf
                    <div>
                        <label class="bp-label">Link type</label>
                        <select name="purpose" class="bp-input">
                            <option value="invoice">Pay bill (choose invoice on open)</option>
                            <option value="wallet">Wallet top-up only</option>
                        </select>
                    </div>
                    @if ($invoices->isNotEmpty())
                        <div>
                            <label class="bp-label">Invoice (optional)</label>
                            <select name="invoice_id" class="bp-input">
                                <option value="">Any / customer chooses</option>
                                @foreach ($invoices as $inv)
                                    <option value="{{ $inv->id }}">{{ $inv->invoice_number }} — {{ number_format($inv->balanceDue(), 2) }} BDT</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label class="bp-label">Fixed amount (optional)</label>
                        <input type="number" name="amount" class="bp-input" step="0.01" min="10" placeholder="Leave empty for full due">
                    </div>
                    <label class="bp-check-row">
                        <input type="checkbox" name="send_sms" value="1" checked>
                        Send link via SMS to {{ $customer->phone }}
                    </label>
                    <button type="submit" class="bp-btn">Create payment link</button>
                </form>

                @if ($recentLinks->isNotEmpty())
                    <h4 class="bp-section-title bp-mt-6">Active links</h4>
                    @foreach ($recentLinks as $plink)
                        <div class="bp-panel-nested">
                            <input type="text" readonly value="{{ $plink->publicUrl() }}" class="bp-input bp-input-sm" onclick="this.select()">
                            <p class="bp-mt-1 bp-inv-meta">Expires {{ $plink->expires_at->format('d M Y') }}
                                @if ($plink->amount) · {{ number_format((float) $plink->amount, 2) }} BDT @endif
                            </p>
                            @if ($customer->phone)
                                <div class="bp-link-row">
                                    <form method="post" action="{{ route('bill-payment.payment-link.sms', $plink) }}">
                                        @csrf
                                        <button type="submit" class="bp-link-inline" style="background:none;border:0;padding:0;cursor:pointer">Resend SMS</button>
                                    </form>
                                    @php $wa = app(\App\Services\BillPayment\PaymentLinkService::class)->whatsAppShareUrl($plink, $customer); @endphp
                                    @if ($wa)
                                        <a href="{{ $wa }}" target="_blank" rel="noopener" class="bp-link-inline">WhatsApp</a>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                @endif
            </div>
        @endif

        <div class="bp-mt-6 bp-text-sm">
            <form method="post" action="{{ route('bill-payment.reset') }}">
                @csrf
                <button type="submit" class="bp-link bg-transparent border-0 p-0 cursor-pointer">Use a different client code</button>
            </form>
        </div>
    </div>
@endsection

<div class="zoom-in pb-4" x-data="{ tab: 'overview' }">
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="h4 fw-bold text-dark mb-0">
                <i class="bi bi-person-badge me-2 text-success"></i>{{ $customer->customer_name }}
            </h2>
            <a href="{{ route('customers.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>{{ __('Back to list') }}
            </a>
        </div>
    </x-slot>

    <div class="card border-0 shadow-sm mb-3 overflow-hidden">
        <div class="card-body p-3 p-md-4" style="background: linear-gradient(135deg, #f0fdf4 0%, #eff6ff 55%, #fff 100%);">
            <div class="row g-3 align-items-start">
                <div class="col-lg-8">
                    <div class="d-flex align-items-start gap-3">
                        @php $initials = mb_strtoupper(mb_substr($customer->customer_name ?? '?', 0, 1, 'UTF-8'), 'UTF-8'); @endphp
                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0"
                             style="width:56px;height:56px;background:#16a34a;font-size:1.25rem;">{{ $initials }}</div>
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <h3 class="h4 fw-bold mb-0">{{ $customer->customer_name }}</h3>
                                <span class="badge {{ $online ? 'bg-success' : 'bg-secondary' }}">{{ $online ? __('Online') : __('Offline') }}</span>
                                <span class="badge bg-{{ $customer->status === 'active' ? 'primary' : ($customer->status === 'disable' ? 'danger' : 'warning') }}">{{ ucfirst($customer->status ?? '—') }}</span>
                                @if($customer->isVip())
                                    <span class="badge bg-warning text-dark"><i class="bi bi-star-fill me-1"></i>VIP</span>
                                @endif
                                @if($customer->isCorporate())
                                    <span class="badge bg-info text-dark"><i class="bi bi-building me-1"></i>{{ __('Corporate') }}</span>
                                @endif
                                @if($customer->official?->customer_type && !$customer->isVip())
                                    <span class="badge bg-light text-dark border">{{ ucfirst($customer->official->customer_type) }}</span>
                                @endif
                                @if($customer->official?->client_type && !$customer->isCorporate())
                                    <span class="badge bg-light text-dark border">{{ $customer->official->client_type }}</span>
                                @endif
                            </div>
                            <div class="text-muted small">
                                <span class="font-monospace fw-semibold text-dark">{{ $customer->customer_unique_id }}</span>
                                @if($customer->mobile) · <a href="tel:{{ $customer->mobile }}" class="text-decoration-none">{{ $customer->mobile }}</a> @endif
                                @if($customer->pppUser?->username) · PPPoE <span class="font-monospace">{{ $customer->pppUser->username }}</span> @endif
                                @if($customer->joinDate())
                                    · {{ __('Joined') }} {{ $customer->joinDateLabel() }}
                                @endif
                            </div>
                            <div class="d-flex flex-wrap gap-2 mt-3 no-print">
                                <a href="{{ route('payment-collection') }}?customer={{ urlencode($encryptedId) }}" class="btn btn-sm btn-success"><i class="bi bi-cash-stack me-1"></i>{{ __('Collect') }}</a>
                                <button type="button" class="btn btn-sm btn-outline-info" wire:click="openSmsModal"><i class="bi bi-chat-dots me-1"></i>{{ __('SMS') }}</button>
                                <a href="{{ route('payment-invoice') }}?customer={{ urlencode($customer->customer_unique_id) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-receipt me-1"></i>{{ __('Invoice') }}</a>
                                <a href="{{ route('customers.edit', $encryptedId) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square me-1"></i>{{ __('Edit') }}</a>
                                @if($customer->pppUser)
                                    <a href="{{ route('staff.subscribers.portal-login', $customer->id) }}" target="_blank" rel="noopener"
                                        class="btn btn-sm btn-info text-white" title="{{ __('Open customer portal as this PPP user') }}">
                                        <i class="bi bi-box-arrow-in-right me-1"></i>{{ __('Portal Login') }}
                                    </a>
                                @endif
                                @if($customer->status !== 'active')
                                    <button type="button" class="btn btn-sm btn-outline-success" wire:click="enableLine" wire:confirm="{{ __('Enable line?') }}"><i class="bi bi-power me-1"></i>{{ __('Net ON') }}</button>
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="disableLine" wire:confirm="{{ __('Disable line?') }}"><i class="bi bi-slash-circle me-1"></i>{{ __('Net OFF') }}</button>
                                @endif
                                <button type="button" class="btn btn-sm btn-outline-warning" wire:click="kickPpp" wire:confirm="{{ __('Kick PPP?') }}"><i class="bi bi-plug me-1"></i>{{ __('Kick') }}</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="extendExpire(5)">+5d</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="extendExpire(30)">+30d</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="bg-white border rounded-3 p-3 h-100 shadow-sm">
                        <div class="text-muted small">{{ __('Wallet / Advance') }}</div>
                        <div class="fw-bold text-success fs-5">{{ number_format($walletBalance, 2) }} BDT</div>
                        <div class="small text-muted mb-2">{{ __('Due') }}: <span class="text-danger fw-semibold">{{ number_format((float)($customer->billing?->due_amount ?? 0), 2) }}</span></div>
                        <div class="d-flex gap-1 mb-2">
                            <button type="button" class="btn btn-sm btn-outline-success" wire:click="addWallet(100)" wire:confirm="{{ __('Add 100 BDT to wallet?') }}">+100</button>
                            <button type="button" class="btn btn-sm btn-outline-success" wire:click="addWallet(500)" wire:confirm="{{ __('Add 500 BDT to wallet?') }}">+500</button>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between small"><span class="text-muted">{{ __('Monthly') }}</span><span class="fw-semibold">{{ number_format((float)($customer->billing?->monthly_rent ?? 0), 2) }}</span></div>
                        <div class="d-flex justify-content-between small"><span class="text-muted">{{ __('Expire') }}</span><span>{{ $customer->billing?->auto_disable_date ? \Carbon\Carbon::parse($customer->billing->auto_disable_date)->format('d M Y') : '—' }}</span></div>
                        <div class="d-flex justify-content-between small"><span class="text-muted">{{ __('First bill') }}</span><span>{{ $firstBillCycle === 'next_month' ? __('Next month') : __('This month') }}</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-pills gap-1 mb-3 flex-wrap">
        @foreach(['overview' => 'grid', 'billing' => 'cash-stack', 'network' => 'hdd-network', 'invoices' => 'receipt', 'map' => 'geo-alt', 'more' => 'three-dots'] as $key => $icon)
            <li class="nav-item">
                <button type="button" class="nav-link" :class="tab==='{{ $key }}' && 'active'" @click="tab='{{ $key }}'; @if($key === 'network') setTimeout(() => window.dispatchEvent(new Event('cv-chart-resize')), 80) @endif">
                    <i class="bi bi-{{ $icon }} me-1"></i>{{ __(ucfirst($key)) }}
                </button>
            </li>
        @endforeach
    </ul>

    {{-- Overview --}}
    <div x-show="tab==='overview'" x-cloak>
        <div class="row g-3">
            <div class="col-md-12">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 fw-semibold">{{ __('Identity') }}</div>
                    <div class="card-body pt-0 small">
                        <div class="row g-2">
                            <div class="col-5 text-muted">{{ __('Name') }}</div><div class="col-7 fw-semibold">{{ $customer->customer_name }}</div>
                            <div class="col-5 text-muted">{{ __('ID') }}</div><div class="col-7 font-monospace">{{ $customer->customer_unique_id }}</div>
                            <div class="col-5 text-muted">{{ __('Mobile') }}</div><div class="col-7">{{ $customer->mobile ?: '—' }}</div>
                            <div class="col-5 text-muted">{{ __('Connection Date') }}</div><div class="col-7">{{ $customer->joinDateLabel() }}</div>
                            <div class="col-5 text-muted">{{ __('Portal Username') }}</div>
                            <div class="col-7 font-monospace">{{ $customer->pppUser?->username ?: '—' }}</div>
                            <div class="col-5 text-muted">{{ __('NID') }}</div><div class="col-7">{{ $customer->identification_no ?: '—' }}</div>
                            <div class="col-5 text-muted">{{ __('Package') }}</div><div class="col-7">{{ $customer->package?->package ?? '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-12">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 fw-semibold">{{ __('Address') }}</div>
                    <div class="card-body pt-0 small">
                        @if($customer->address)<div class="mb-2">{{ $customer->address }}</div>@endif
                        @forelse($addressLines as $line)<div class="text-muted">{{ $line }}</div>@empty
                            @unless($customer->address)<div class="text-muted">{{ __('No address') }}</div>@endunless
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Billing --}}
    <div x-show="tab==='billing'" x-cloak>
        @php $b = $customer->billing; @endphp
        <div class="card border-0 shadow-sm">
            <div class="card-body small">
                <div class="row g-2">
                    <div class="col-md-3"><span class="text-muted">{{ __('Rent') }}</span><div class="fw-bold">{{ number_format((float)($b?->monthly_rent ?? 0), 2) }}</div></div>
                    <div class="col-md-3"><span class="text-muted">{{ __('Due') }}</span><div class="fw-bold text-danger">{{ number_format((float)($b?->due_amount ?? 0), 2) }}</div></div>
                    <div class="col-md-3"><span class="text-muted">{{ __('Advance') }}</span><div class="fw-bold text-success">{{ number_format((float)($b?->advance ?? 0), 2) }}</div></div>
                    <div class="col-md-3"><span class="text-muted">{{ __('Bill day') }}</span><div class="fw-bold">{{ $b?->billing_day ?: '—' }}</div></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Network: PPP + Live traffic + ONU --}}
    <div x-show="tab==='network'" x-cloak>
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 fw-semibold d-flex justify-content-between">
                        <span><i class="bi bi-activity me-1 text-success"></i>{{ __('Live traffic') }}</span>
                        @if($online)<span class="badge bg-success">{{ __('Polling') }}</span>@endif
                    </div>
                    <div class="card-body">
                        @if($online && $trafficRouter && $trafficInterface)
                            <div wire:poll.2000ms="pollTraffic" class="d-none"></div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-success"><i class="bi bi-arrow-down-circle me-1"></i>{{ __('Download') }}</span>
                                <strong class="text-success" id="cv-rx-label">{{ number_format($rxSpeed / 1_000_000, 2) }} Mbps</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="text-primary"><i class="bi bi-arrow-up-circle me-1"></i>{{ __('Upload') }}</span>
                                <strong class="text-primary" id="cv-tx-label">{{ number_format($txSpeed / 1_000_000, 2) }} Mbps</strong>
                            </div>
                            <div wire:ignore
                                 class="border rounded bg-light"
                                 x-data="customerViewTrafficChart()"
                                 x-init="$nextTick(() => initChart())"
                                 @customer-traffic-updated.window="updateTraffic($event.detail)"
                                 @cv-chart-resize.window="resizeChart()">
                                <div x-ref="chartContainer" style="width:100%;min-height:220px;height:220px;"></div>
                            </div>
                            <div class="small text-muted mt-2 font-monospace">{{ $trafficInterface }} @ {{ $trafficRouter }}</div>
                        @else
                            <div class="text-muted py-4 text-center">{{ __('Customer offline or no PPP linked — live traffic unavailable.') }}</div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white border-0 fw-semibold">{{ __('PPPoE') }}</div>
                    <div class="card-body pt-0 small">
                        @php $p = $customer->pppUser; @endphp
                        <div>{{ __('User') }}: <span class="font-monospace fw-bold">{{ $p?->username ?: '—' }}</span></div>
                        <div>{{ __('Router') }}: {{ $p?->router_name ?: '—' }}</div>
                        <div>{{ __('MAC') }}: <span class="font-monospace">{{ $p?->caller_id ?: '—' }}</span></div>
                        <div>{{ __('IP') }}: <span class="font-monospace">{{ $p?->ppp_remote_ip ?: '—' }}</span></div>
                    </div>
                </div>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 fw-semibold d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-broadcast-pin me-1 text-success"></i>{{ __('ONU / Laser') }}</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="syncOnu" wire:loading.attr="disabled">
                            <span wire:loading wire:target="syncOnu" class="spinner-border spinner-border-sm"></span>
                            {{ __('Auto-sync') }}
                        </button>
                    </div>
                    <div class="card-body pt-0 small">
                        @if($optical['linked'] ?? false)
                            <div class="alert alert-success py-2 mb-2">
                                RX <strong>{{ $optical['row']['optical_power'] ?? '—' }}</strong> dBm ·
                                TX <strong>{{ $optical['row']['tx_power'] ?? '—' }}</strong> dBm<br>
                                OLT: {{ $optical['row']['olt_name'] ?? '—' }} · PON: {{ $optical['row']['olt_port'] ?? '—' }}
                                @if(!empty($optical['details']['last_polled_at']))<br><span class="text-muted">{{ $optical['details']['last_polled_at'] }}</span>@endif
                            </div>
                        @else
                            <div class="text-muted mb-2">{{ $optical['hint'] ?? __('No ONU data') }}</div>
                        @endif
                        @if(!$opticalBridgeEnabled)
                            <div class="text-muted small mb-2"><i class="bi bi-info-circle"></i> {{ __('Set ISPBILLING_OPTICAL_BRIDGE=true for OLT auto-sync, or enter manually below.') }}</div>
                        @endif
                        <div class="row g-2">
                            <div class="col-6"><input type="text" class="form-control form-control-sm" placeholder="MAC" wire:model="onuMac"></div>
                            <div class="col-6"><input type="text" class="form-control form-control-sm" placeholder="PON" wire:model="onuPon"></div>
                            <div class="col-4"><input type="text" class="form-control form-control-sm" placeholder="RX dBm" wire:model="onuRx"></div>
                            <div class="col-4"><input type="text" class="form-control form-control-sm" placeholder="TX dBm" wire:model="onuTx"></div>
                            <div class="col-4"><input type="text" class="form-control form-control-sm" placeholder="OLT" wire:model="onuOlt"></div>
                        </div>
                        <button type="button" class="btn btn-sm btn-success mt-2" wire:click="saveOnuManual">{{ __('Save optical') }}</button>
                        @if(!empty($optical['history']))
                            <div class="mt-3 border-top pt-2">
                                <div class="text-muted small fw-semibold mb-1">{{ __('RX / TX history') }}</div>
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach(array_slice($optical['history'], 0, 8) as $h)
                                        <span class="badge bg-light text-dark border font-monospace" style="font-size:.7rem;">
                                            {{ $h['at'] }}: {{ $h['rx'] ?? '—' }}/{{ $h['tx'] ?? '—' }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Invoices --}}
    <div x-show="tab==='invoices'" x-cloak>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 fw-semibold">{{ __('Invoice & collection history') }}</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>{{ __('Date') }}</th><th>{{ __('Invoice #') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('By') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                    @forelse($collections as $col)
                        <tr>
                            <td>{{ $col->collection_date ? \Carbon\Carbon::parse($col->collection_date)->format('d M Y H:i') : '—' }}</td>
                            <td class="font-monospace">{{ $col->invoice_no ? '#'.siteUrlSettings('site_invoice_prefix').$col->invoice_no : '—' }}</td>
                            <td class="text-end fw-semibold text-success">{{ number_format((float)($col->collection_amount ?? 0), 2) }}</td>
                            <td>{{ $col->collected_by ?? '—' }}</td>
                            <td><span class="badge bg-success">{{ $col->payment_status ?? 'paid' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">{{ __('No invoices yet') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white border-0 fw-semibold">{{ __('Monthly bills') }}</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>{{ __('Month') }}</th><th class="text-end">{{ __('Rent') }}</th><th class="text-end">{{ __('Paid') }}</th><th class="text-end">{{ __('Due') }}</th></tr></thead>
                    <tbody>
                    @forelse($payments as $p)
                        <tr>
                            <td>{{ $p->summary_date ? \Carbon\Carbon::parse($p->summary_date)->format('M Y') : '—' }}</td>
                            <td class="text-end">{{ number_format((float)($p->monthly_rent ?? 0), 2) }}</td>
                            <td class="text-end text-success">{{ number_format((float)($p->paid_amount ?? 0), 2) }}</td>
                            <td class="text-end text-danger">{{ number_format((float)($p->due_amount ?? 0), 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">{{ __('No monthly bills') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- GPS Map --}}
    <div x-show="tab==='map'" x-cloak>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 fw-semibold"><i class="bi bi-geo-alt me-1 text-success"></i>{{ __('Customer location') }}</div>
            <div class="card-body">
                @if($gps)
                    <iframe title="map" class="w-100 rounded border" style="height:360px;border:0;"
                        src="https://www.openstreetmap.org/export/embed.html?bbox={{ $gps['lng']-0.01 }}%2C{{ $gps['lat']-0.01 }}%2C{{ $gps['lng']+0.01 }}%2C{{ $gps['lat']+0.01 }}&layer=mapnik&marker={{ $gps['lat'] }}%2C{{ $gps['lng'] }}"></iframe>
                    <div class="mt-2 small">
                        {{ $gps['lat'] }}, {{ $gps['lng'] }}
                        <a href="https://www.google.com/maps?q={{ $gps['lat'] }},{{ $gps['lng'] }}" target="_blank" rel="noopener" class="ms-2">{{ __('Open in Google Maps') }}</a>
                    </div>
                @else
                    <div class="text-muted py-5 text-center">
                        {{ __('No GPS coordinates. Add latitude/longitude when creating or editing the customer.') }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- More --}}
    <div x-show="tab==='more'" x-cloak>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 fw-semibold">{{ __('Tags & billing flags') }}</div>
                    <div class="card-body pt-0 small">
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            @if($customer->isVip())<span class="badge bg-warning text-dark">VIP</span>@endif
                            @if($customer->isCorporate())<span class="badge bg-info text-dark">Corporate</span>@endif
                            <span class="badge {{ $customer->official?->bill_create ? 'bg-success' : 'bg-light text-dark border' }}">{{ __('Auto bill') }}</span>
                            <span class="badge {{ $customer->official?->bill_sms ? 'bg-success' : 'bg-light text-dark border' }}">{{ __('Bill SMS') }}</span>
                            <span class="badge {{ $customer->official?->continue_bill ? 'bg-success' : 'bg-light text-dark border' }}">{{ __('Continue bill') }}</span>
                        </div>
                        <div class="text-muted">{{ $customer->official?->note ?: '—' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 fw-semibold">{{ __('Support tickets') }}</div>
                    <ul class="list-group list-group-flush small">
                        @forelse($tickets as $t)
                            <li class="list-group-item d-flex justify-content-between">#{{ $t->id }} {{ $t->subject ?? $t->title ?? '' }}<span class="badge bg-light text-dark border">{{ $t->status ?? '—' }}</span></li>
                        @empty
                            <li class="list-group-item text-muted">{{ __('No tickets') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>

    @if($showSmsModal)
        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">{{ __('Send SMS') }}</h5><button type="button" class="btn-close" wire:click="$set('showSmsModal', false)"></button></div>
                    <div class="modal-body">
                        <div class="small text-muted mb-2">{{ __('To') }}: {{ $customer->mobile }}</div>
                        <textarea class="form-control" rows="4" wire:model="smsMessage"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="$set('showSmsModal', false)">{{ __('Cancel') }}</button>
                        <button type="button" class="btn btn-success" wire:click="sendSms" wire:loading.attr="disabled">{{ __('Send') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<style>
    [x-cloak] { display: none !important; }
    .nav-pills .nav-link { color: #334155; background: #f8fafc; border-radius: 999px; padding: .35rem .9rem; font-size: .875rem; }
    .nav-pills .nav-link.active { background: #16a34a; color: #fff; }
</style>

@script
<script>
    window.customerViewTrafficChart = function () {
        return {
            chart: null,
            dataRx: [],
            dataTx: [],
            maxPoints: 120,
            initChart() {
                if (!window.ApexCharts || !this.$refs.chartContainer) {
                    return;
                }
                if (this.chart) {
                    this.chart.destroy();
                    this.chart = null;
                }

                this.dataRx = [];
                this.dataTx = [];
                const now = Date.now();
                for (let i = 60; i > 0; i--) {
                    const ts = now - (i * 2000);
                    this.dataRx.push([ts, 0]);
                    this.dataTx.push([ts, 0]);
                }

                const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark'
                    || document.documentElement.classList.contains('dark');

                this.chart = new ApexCharts(this.$refs.chartContainer, {
                    series: [
                        { name: '{{ __('Download') }}', data: this.dataRx },
                        { name: '{{ __('Upload') }}', data: this.dataTx },
                    ],
                    chart: {
                        type: 'area',
                        height: 220,
                        animations: { enabled: true, easing: 'linear', dynamicAnimation: { speed: 800 } },
                        toolbar: { show: false },
                        zoom: { enabled: false },
                    },
                    theme: { mode: isDark ? 'dark' : 'light' },
                    colors: ['#198754', '#0d6efd'],
                    dataLabels: { enabled: false },
                    stroke: { curve: 'smooth', width: 2 },
                    fill: {
                        type: 'gradient',
                        gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05, stops: [0, 100] },
                    },
                    xaxis: {
                        type: 'datetime',
                        labels: { show: true, datetimeUTC: false, format: 'HH:mm:ss' },
                    },
                    yaxis: {
                        labels: {
                            formatter: (v) => v >= 1 ? v.toFixed(1) + ' Mbps' : (v * 1024).toFixed(0) + ' Kbps',
                        },
                        min: 0,
                    },
                    legend: { position: 'top', horizontalAlign: 'left' },
                    tooltip: {
                        x: { format: 'HH:mm:ss' },
                        y: {
                            formatter: (v) => v >= 1 ? v.toFixed(2) + ' Mbps' : (v * 1024).toFixed(1) + ' Kbps',
                        },
                    },
                });

                this.chart.render();
            },
            resizeChart() {
                if (!this.chart) {
                    this.initChart();
                    return;
                }
                this.chart.resize();
            },
            updateTraffic(detail) {
                const evt = Array.isArray(detail) ? detail[0] : detail;
                const rxMbps = (evt?.rx || 0) / 1048576;
                const txMbps = (evt?.tx || 0) / 1048576;
                const now = Date.now();

                const rxLabel = document.getElementById('cv-rx-label');
                const txLabel = document.getElementById('cv-tx-label');
                if (rxLabel) {
                    rxLabel.textContent = rxMbps >= 1
                        ? rxMbps.toFixed(2) + ' Mbps'
                        : (rxMbps * 1024).toFixed(0) + ' Kbps';
                }
                if (txLabel) {
                    txLabel.textContent = txMbps >= 1
                        ? txMbps.toFixed(2) + ' Mbps'
                        : (txMbps * 1024).toFixed(0) + ' Kbps';
                }

                if (!this.chart) {
                    return;
                }

                this.dataRx.push([now, rxMbps]);
                if (this.dataRx.length > this.maxPoints) {
                    this.dataRx.shift();
                }
                this.dataTx.push([now, txMbps]);
                if (this.dataTx.length > this.maxPoints) {
                    this.dataTx.shift();
                }

                this.chart.updateSeries([
                    { data: this.dataRx },
                    { data: this.dataTx },
                ]);
            },
        };
    };
</script>
@endscript

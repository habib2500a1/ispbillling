<div wire:poll.5s="poll">
    <x-slot name="header">
        {{ __('Bandwidth Hub') }}
    </x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="text-muted small">
            {{ __('Live network overview') }} · {{ __('Updated') }}: {{ \Carbon\Carbon::parse($updated_at)->diffForHumans() }}
            <span class="badge bg-light text-dark border ms-1">{{ __('poll 5s') }}</span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="refresh">
                <i class="bi bi-arrow-repeat"></i> {{ __('Refresh') }}
            </button>
            <a href="{{ route('mikrotik-traffic-monitor') }}" class="btn btn-sm btn-outline-secondary">{{ __('Traffic Monitor') }}</a>
            <a href="{{ route('mikrotik-sync') }}" class="btn btn-sm btn-primary">{{ __('Routers') }}</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#0f172a,#1e3a5f);">
                <div class="card-body">
                    <div class="small text-uppercase opacity-75">{{ __('Routers') }}</div>
                    <div class="fs-3 fw-bold">{{ $stats['connected'] }} <span class="fs-6 fw-normal opacity-75">/ {{ $stats['routers'] }}</span></div>
                    <div class="small opacity-75">{{ __('Connected') }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#059669,#047857);">
                <div class="card-body">
                    <div class="small text-uppercase opacity-75">{{ __('PPP secrets') }}</div>
                    <div class="fs-3 fw-bold">{{ number_format($stats['ppp_total']) }}</div>
                    <div class="small opacity-75">{{ __('DB online-ish') }}: {{ $stats['ppp_online_db'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#4f46e5,#4338ca);">
                <div class="card-body">
                    <div class="small text-uppercase opacity-75">{{ __('Live PPP active') }}</div>
                    <div class="fs-3 fw-bold">{{ $stats['ppp_active_live'] === null ? '—' : number_format($stats['ppp_active_live']) }}</div>
                    <div class="small opacity-75">{{ __('From selected router') }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#b45309,#92400e);">
                <div class="card-body">
                    <div class="small text-uppercase opacity-75">{{ __('Live RX / TX') }}</div>
                    <div class="fs-4 fw-bold">{{ number_format($live['rx_mbps'], 2) }} <span class="fs-6 fw-normal">/ {{ number_format($live['tx_mbps'], 2) }}</span></div>
                    <div class="small opacity-75">Mbps</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-2 mb-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label small mb-0">{{ __('Router') }}</label>
            <select class="form-select form-select-sm" wire:model.live="selectedRouter">
                @foreach($routers as $r)
                    <option value="{{ $r['name'] }}">
                        {{ $r['name'] }} ({{ $r['ip'] }}) {{ $r['connected'] ? '●' : '○' }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small mb-0">{{ __('Interface') }}</label>
            <select class="form-select form-select-sm" wire:model.live="selectedInterface" @disabled(empty($interfaces))>
                @forelse($interfaces as $iface)
                    <option value="{{ $iface }}">{{ $iface }}</option>
                @empty
                    <option value="">{{ __('No interfaces (router offline?)') }}</option>
                @endforelse
            </select>
        </div>
        <div class="col-md-4 small text-muted">
            @if($live['error'])
                <span class="text-warning">{{ \Illuminate\Support\Str::limit($live['error'], 80) }}</span>
            @else
                {{ __('Chart builds while polling a connected router.') }}
            @endif
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-0 pt-3 d-flex justify-content-between">
                    <h6 class="fw-bold mb-0">{{ __('Live chart (Mbps)') }}</h6>
                    <span class="small text-muted">RX {{ number_format($live['rx_mbps'], 3) }} · TX {{ number_format($live['tx_mbps'], 3) }}</span>
                </div>
                <div class="card-body pt-0">
                    @php
                        $rxMax = max(0.001, ...(count($chart['rx_mbps']) ? $chart['rx_mbps'] : [0]));
                        $txMax = max(0.001, ...(count($chart['tx_mbps']) ? $chart['tx_mbps'] : [0]));
                        $ymax = max($rxMax, $txMax);
                    @endphp
                    @if(count($chart['labels']) === 0)
                        <div class="text-muted small py-5 text-center">{{ __('Waiting for live samples… Connect a MikroTik router to populate.') }}</div>
                    @else
                        <div class="d-flex align-items-end gap-1" style="height:160px;">
                            @foreach($chart['rx_mbps'] as $i => $rx)
                                @php
                                    $tx = $chart['tx_mbps'][$i] ?? 0;
                                    $hRx = max(2, (int) round(($rx / $ymax) * 140));
                                    $hTx = max(2, (int) round(($tx / $ymax) * 140));
                                @endphp
                                <div class="d-flex flex-column justify-content-end align-items-center" style="flex:1;min-width:4px;height:100%;" title="{{ $chart['labels'][$i] ?? '' }} RX {{ $rx }} TX {{ $tx }}">
                                    <div class="w-100 rounded-top" style="height:{{ $hRx }}px;background:#059669;opacity:.9;"></div>
                                    <div class="w-100 rounded-top mt-1" style="height:{{ $hTx }}px;background:#4f46e5;opacity:.85;"></div>
                                </div>
                            @endforeach
                        </div>
                        <div class="d-flex justify-content-between small text-muted mt-2">
                            <span>{{ $chart['labels'][0] ?? '' }}</span>
                            <span>
                                <span class="me-2"><i class="bi bi-square-fill" style="color:#059669"></i> RX</span>
                                <span><i class="bi bi-square-fill" style="color:#4f46e5"></i> TX</span>
                            </span>
                            <span>{{ end($chart['labels']) ?: '' }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-3">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold mb-0">{{ __('Routers') }}</h6>
                </div>
                <div class="card-body pt-0">
                    @forelse($routers as $r)
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div>
                                <div class="fw-semibold">{{ $r['name'] }}</div>
                                <div class="small text-muted">{{ $r['ip'] }}</div>
                            </div>
                            <span class="badge bg-{{ $r['connected'] ? 'success' : 'secondary' }}">
                                {{ $r['connected'] ? __('Connected') : __('Offline') }}
                            </span>
                        </div>
                    @empty
                        <div class="text-muted small">{{ __('No routers configured.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold mb-0">{{ __('Packages by speed') }}</h6>
                </div>
                <div class="card-body pt-0">
                    @forelse($packages as $row)
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-truncate" style="max-width:70%;">{{ $row['label'] }}</span>
                            <strong>{{ $row['count'] }}</strong>
                        </div>
                    @empty
                        <div class="text-muted small">{{ __('No packages yet.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold mb-0">{{ __('PPP secrets by router') }}</h6>
                </div>
                <div class="card-body pt-0">
                    @forelse($ppp_by_router as $row)
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span>{{ $row['router'] }}</span>
                            <strong>{{ $row['count'] }}</strong>
                        </div>
                    @empty
                        <div class="text-muted small">{{ __('No PPP secrets synced.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

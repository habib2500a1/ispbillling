@extends('reseller.layout')

@section('title', 'Sub-partners')

@section('content')
    @php $portal = app(\App\Support\ResellerPortalSession::class); @endphp

    @include('reseller.partials.page-header', [
        'title' => 'Sub-partners',
        'subtitle' => $partners->count().' partner(s) in your hierarchy',
        'actionUrl' => $portal->canPortal(\App\Support\ResellerPortalPermission::SUB_RESELLER_CREATE) ? route('reseller.sub-resellers.create') : null,
        'actionLabel' => $portal->canPortal(\App\Support\ResellerPortalPermission::SUB_RESELLER_CREATE) ? '+ New Partners' : null,
    ])

    <div class="rsl-panel">
        <div class="rsl-table-wrap">
            <table class="rsl-table w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Subscribers</th>
                        <th class="px-4 py-3">Wallet</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($partners as $partner)
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('reseller.sub-resellers.show', $partner) }}" class="rsl-link-action font-mono">{{ $partner->code }}</a>
                            </td>
                            <td class="px-4 py-3">{{ $partner->name }}</td>
                            <td class="px-4 py-3">{{ $partner->franchiseTypeLabel() }}</td>
                            <td class="px-4 py-3">{{ $partner->customers_count }}</td>
                            <td class="px-4 py-3">{{ number_format((float) $partner->wallet_balance, 0) }} BDT</td>
                            <td class="px-4 py-3">
                                <span class="rsl-badge-pill {{ $partner->is_active ? 'rsl-badge-pill--ok' : 'rsl-badge-pill--muted' }}">
                                    {{ $partner->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center" style="color:var(--rsl-text-muted)">
                                No sub-partners. Ask HQ to add one or create below.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

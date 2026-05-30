@extends('reseller.layout')

@section('title', 'Sub-partners')

@section('content')
    <div class="rsl-card p-6">
        <h1 class="rsl-title">Sub-partners</h1>
        <p class="rsl-subtitle">{{ $partners->count() }} partner(s) under your account</p>
    </div>
    <div class="rsl-card mt-6 overflow-hidden">
        <table class="rsl-table w-full text-sm">
            <thead>
                <tr>
                    <th class="px-4 py-3">Code</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Clients</th>
                    <th class="px-4 py-3">Wallet</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($partners as $partner)
                    <tr>
                        <td class="px-4 py-3"><a href="{{ route('reseller.sub-resellers.show', $partner) }}" class="rsl-link font-mono">{{ $partner->code }}</a></td>
                        <td class="px-4 py-3 rsl-text">{{ $partner->name }}</td>
                        <td class="px-4 py-3">{{ $partner->franchiseTypeLabel() }}</td>
                        <td class="px-4 py-3">{{ $partner->customers_count }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $partner->wallet_balance, 0) }} BDT</td>
                        <td class="px-4 py-3">{{ $partner->is_active ? 'Active' : 'Inactive' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center rsl-text-muted">No sub-partners assigned. Contact HQ to add partners under your account.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

@extends('reseller.layout')

@section('title', 'Commissions')

@section('content')
    <div class="rsl-card p-6">
        <h1 class="text-2xl font-bold text-slate-900">Commission ledger</h1>
        <p class="mt-2 text-sm text-slate-600">
            Pending: <strong class="text-amber-700">{{ number_format($totals['pending'], 2) }} BDT</strong>
            · Paid: <strong class="text-emerald-700">{{ number_format($totals['paid'], 2) }} BDT</strong>
        </p>
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach (['' => 'All', 'pending' => 'Pending', 'paid' => 'Paid', 'cancelled' => 'Cancelled'] as $key => $label)
                <a href="{{ route('reseller.commissions.index', $key ? ['status' => $key] : []) }}"
                   class="rounded-full px-3 py-1 text-sm font-semibold {{ $status === $key ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="rsl-card mt-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="rsl-table w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50">
                    <tr>
                        <th class="px-4 py-3">Earned</th>
                        <th class="px-4 py-3">Subscriber</th>
                        <th class="px-4 py-3">Gross</th>
                        <th class="px-4 py-3">Commission</th>
                        <th class="px-4 py-3">Method</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($commissions as $row)
                        <tr class="border-b border-slate-100">
                            <td class="px-4 py-3">{{ $row->earned_at?->format('d M Y H:i') ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $row->customer?->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ number_format((float) $row->gross_amount, 2) }} BDT</td>
                            <td class="px-4 py-3 font-semibold text-emerald-700">{{ number_format((float) $row->commission_amount, 2) }} BDT</td>
                            <td class="px-4 py-3 capitalize">{{ $row->payment?->method ?? '—' }}</td>
                            <td class="px-4 py-3 capitalize">{{ $row->status }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">No commission records.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($commissions->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">{{ $commissions->links() }}</div>
        @endif
    </div>
@endsection

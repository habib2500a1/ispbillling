@extends('reseller.layout')

@section('title', 'Subscribers')

@section('content')
    <div class="rsl-card p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="rsl-title">Your subscribers</h1>
                <p class="rsl-subtitle">{{ $customers->total() }} assigned to {{ $reseller->code }}</p>
                @if ($dueCustomerCount > 0)
                    <p class="mt-2 text-sm font-semibold text-rose-700">
                        {{ $dueCustomerCount }} with due · {{ number_format($totalDue, 2) }} BDT total outstanding
                    </p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($portal->canPortal(\App\Support\ResellerPortalPermission::CUSTOMER_CREATE))
                    <a href="{{ route('reseller.customers.create') }}" class="rsl-btn-sm">+ New subscriber</a>
                @endif
                @if ($portal->canPortal(\App\Support\ResellerPortalPermission::INVOICE_GENERATE) && config('reseller_billing.portal_bulk_invoice_generate', true))
                    <a href="{{ route('reseller.invoices.index') }}" class="rsl-btn-sm rsl-btn-sm--outline">Generate monthly bills</a>
                @endif
                @if ($portal->canPortal(\App\Support\ResellerPortalPermission::CUSTOMER_TRANSFER))
                    <a href="{{ route('reseller.customer-transfers.index') }}" class="rsl-btn-sm rsl-btn-sm--outline">Transfers</a>
                @endif
                <a href="{{ route('reseller.customers.index', array_filter(['due' => 1, 'q' => $search ?: null])) }}"
                   class="rsl-btn-sm {{ $dueOnly ? 'rsl-btn-sm' : 'rsl-btn-sm--outline' }}">
                    Due only
                    @if ($dueCustomerCount > 0)
                        ({{ $dueCustomerCount }})
                    @endif
                </a>
                @if ($dueOnly)
                    <a href="{{ route('reseller.customers.index', array_filter(['q' => $search ?: null])) }}" class="rsl-btn-sm rsl-btn-sm--outline">All</a>
                @endif
                @if ($portal->canPortal(\App\Support\ResellerPortalPermission::BILLING_VIEW) && $dueCustomerCount > 0 && config('reseller_billing.due_reminders.reseller_portal_enabled', true))
                    <form method="post" action="{{ route('reseller.due-reminders.bulk') }}" class="inline"
                          onsubmit="return confirm('Send due reminders to all subscribers with open bills? Each bill is limited to once per {{ config('reseller_billing.due_reminders.cooldown_hours', 24) }} hours.');">
                        @csrf
                        <button type="submit" class="rsl-btn-sm rsl-btn-sm--outline">Remind all due</button>
                    </form>
                @endif
                <form method="get" class="flex gap-2">
                    @if ($dueOnly)
                        <input type="hidden" name="due" value="1">
                    @endif
                    <input type="search" name="q" value="{{ $search }}" placeholder="Search" class="rsl-input max-w-xs">
                    <button type="submit" class="rsl-btn-sm rsl-btn-sm--outline">Search</button>
                </form>
            </div>
        </div>
    </div>

    <div class="rsl-card mt-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="rsl-table w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50">
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Phone</th>
                        <th class="px-4 py-3">Package</th>
                        <th class="px-4 py-3">Due (BDT)</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        @php $due = app(\App\Services\Resellers\ResellerSuspendedBillingService::class)->displayableOpenDue($customer); @endphp
                        <tr class="border-b border-slate-100 {{ $due > 0 ? 'bg-rose-50/60' : '' }}">
                            <td class="px-4 py-3 font-mono text-xs">{{ $customer->customer_code }}</td>
                            <td class="px-4 py-3 font-medium">{{ $customer->name }}</td>
                            <td class="px-4 py-3">{{ $customer->phone ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $customer->package?->name ?? '—' }}</td>
                            <td class="px-4 py-3 font-semibold {{ $due > 0 ? 'text-rose-700' : 'text-slate-400' }}">
                                {{ $due > 0 ? number_format($due, 2) : '—' }}
                            </td>
                            <td class="px-4 py-3 capitalize">{{ $customer->status }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('reseller.customers.show', $customer) }}" class="text-indigo-600 font-semibold">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-500">No subscribers assigned yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($customers->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">{{ $customers->links() }}</div>
        @endif
    </div>
@endsection

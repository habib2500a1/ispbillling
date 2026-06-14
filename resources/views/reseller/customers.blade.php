@extends('reseller.layout')

@section('title', 'Subscribers')

@section('content')
    @include('reseller.partials.page-header', [
        'title' => 'Your subscribers',
        'subtitle' => $customers->total().'  ·  '.$reseller->code.($dueCustomerCount > 0 ? ' · Due '.$dueCustomerCount.' ('.number_format($totalDue, 2).' BDT)' : ''),
        'actionUrl' => $portal->canPortal(\App\Support\ResellerPortalPermission::CUSTOMER_CREATE) ? route('reseller.customers.create') : null,
        'actionLabel' => $portal->canPortal(\App\Support\ResellerPortalPermission::CUSTOMER_CREATE) ? '+ New' : null,
    ])

    <div class="rsl-panel rsl-panel-pad">
        <div class="rsl-toolbar">
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
                    <a href="{{ route('reseller.customers.index', array_filter(['q' => $search ?: null, 'tag' => $tag ?: null])) }}" class="rsl-btn-sm rsl-btn-sm--outline">All</a>
                @endif
                @foreach ($tagOptions ?? [] as $tagKey => $tagLabel)
                    <a href="{{ route('reseller.customers.index', array_filter(['tag' => $tagKey, 'due' => $dueOnly ? 1 : null, 'q' => $search ?: null])) }}"
                       class="rsl-btn-sm {{ ($tag ?? '') === $tagKey ? '' : 'rsl-btn-sm--outline' }}">{{ $tagLabel }}</a>
                @endforeach
                @if ($portal->canPortal(\App\Support\ResellerPortalPermission::BILLING_VIEW) && $dueCustomerCount > 0 && config('reseller_billing.due_reminders.reseller_portal_enabled', true))
                    <form method="post" action="{{ route('reseller.due-reminders.bulk') }}" class="inline"
                          onsubmit="return confirm('Send due reminders to all subscribers with open bills? Each bill is limited to once per {{ config('reseller_billing.due_reminders.cooldown_hours', 24) }} hours.');">
                        @csrf
                        <button type="submit" class="rsl-btn-sm rsl-btn-sm--outline">Remind all due</button>
                    </form>
                @endif
                <form method="get" class="rsl-toolbar-search">
                    @if ($dueOnly)
                        <input type="hidden" name="due" value="1">
                    @endif
                    @if (! empty($tag))
                        <input type="hidden" name="tag" value="{{ $tag }}">
                    @endif
                    <input type="search" name="q" value="{{ $search }}" placeholder="Search…" class="rsl-input">
                    <button type="submit" class="rsl-btn-sm rsl-btn-sm--outline">Search</button>
                </form>
        </div>
    </div>

    <div class="rsl-panel mt-4 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="rsl-table w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50">
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Phone</th>
                        <th class="px-4 py-3">PPPoE</th>
                        <th class="px-4 py-3">Package</th>
                        <th class="px-4 py-3">Bill/mo</th>
                        <th class="px-4 py-3">Due (BDT)</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        @php
                            $due = app(\App\Services\Resellers\ResellerSuspendedBillingService::class)->displayableOpenDue($customer);
                            $monthly = ($pricingService ?? null)?->effectiveRetailMonthly($customer) ?? 0;
                        @endphp
                        <tr class="border-b border-slate-100 {{ $due > 0 ? 'bg-rose-50/60' : '' }}">
                            <td class="px-4 py-3 font-mono text-xs">{{ $customer->customer_code }}</td>
                            <td class="px-4 py-3 font-medium">
                                {{ $customer->name }}
                                @php $m = is_array($customer->meta) ? $customer->meta : []; @endphp
                                @if (! empty($m['tag_vip']))<span class="rsl-tag-pill rsl-tag-pill--vip">VIP</span>@endif
                                @if (! empty($m['tag_late_payer']))<span class="rsl-tag-pill rsl-tag-pill--late_payer">Late</span>@endif
                            </td>
                            <td class="px-4 py-3">{{ $customer->phone ?? '—' }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $customer->mikrotik_secret_name ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $customer->package?->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $monthly > 0 ? number_format($monthly, 0) : '—' }}</td>
                            <td class="px-4 py-3 font-semibold {{ $due > 0 ? 'text-rose-700' : 'text-slate-400' }}">
                                {{ $due > 0 ? number_format($due, 2) : '—' }}
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $statusBadge = match ($customer->status) {
                                        'active' => 'rsl-badge--success',
                                        'suspended' => 'rsl-badge--danger',
                                        default => 'rsl-badge--info',
                                    };
                                @endphp
                                <span class="rsl-badge {{ $statusBadge }}">{{ ucfirst($customer->status) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    @if ($portal->canPortal(\App\Support\ResellerPortalPermission::PAYMENT_COLLECT) && $due > 0)
                                        <a href="{{ route('reseller.customers.collect', $customer) }}" class="rsl-btn-sm">Collect</a>
                                    @endif
                                    <a href="{{ route('reseller.customers.show', $customer) }}" class="rsl-link font-semibold">Open</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-slate-500">No subscribers assigned yet.</td>
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

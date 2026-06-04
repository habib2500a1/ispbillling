@extends('reseller.layout')

@section('title', 'Enterprise reports')

@section('content')
    @include('reseller.partials.page-header', [
        'title' => 'Enterprise analytics',
        'subtitle' => 'Revenue, growth, packages, and profit/loss this month.',
    ])

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rsl-panel rsl-panel-pad">
            <h2 class="rsl-heading">Revenue (commission)</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt>Gross</dt><dd>{{ number_format($revenue['gross'], 2) }} BDT</dd></div>
                <div class="flex justify-between"><dt>Commission</dt><dd>{{ number_format($revenue['commission'], 2) }} BDT</dd></div>
                <div class="flex justify-between"><dt>Paid</dt><dd>{{ number_format($revenue['paid'], 2) }} BDT</dd></div>
                <div class="flex justify-between"><dt>Pending</dt><dd>{{ number_format($revenue['pending'], 2) }} BDT</dd></div>
            </dl>
        </div>
        <div class="rsl-panel rsl-panel-pad">
            <h2 class="rsl-heading">Profit / loss estimate</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt>Collections</dt><dd>{{ number_format($profitLoss['collections'], 2) }}</dd></div>
                <div class="flex justify-between"><dt>Wholesale cost</dt><dd>{{ number_format($profitLoss['wholesale_cost'], 2) }}</dd></div>
                <div class="flex justify-between font-semibold"><dt>Est. profit</dt><dd>{{ number_format($profitLoss['estimated_profit'], 2) }} BDT</dd></div>
            </dl>
        </div>
    </div>

    @if (!empty($packageSales['packages']))
        <div class="rsl-panel rsl-panel-pad mt-6">
            <h2 class="rsl-heading">Package sales</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($packageSales['packages'] as $pkg)
                    <li class="flex justify-between border-b border-slate-100 py-2">
                        <span>{{ $pkg['name'] }}</span>
                        <span>{{ number_format($pkg['total'], 2) }} BDT ({{ $pkg['payments'] }} payments)</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection

@extends('reseller.layout')

@section('title', 'Transfer customer')

@section('content')
    <div class="rsl-card p-6 max-w-lg">
        <h1 class="rsl-title">Transfer subscriber</h1>
        <p class="rsl-subtitle">{{ $customer->name }} ({{ $customer->customer_code }})</p>
        <form method="post" action="{{ route('reseller.customer-transfers.store', $customer) }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="text-sm rsl-text-muted">Transfer to</label>
                <select name="to_reseller_id" required class="rsl-input mt-1 w-full">
                    @foreach ($targets as $target)
                        <option value="{{ $target->id }}">{{ $target->name }} ({{ $target->code }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm rsl-text-muted">Reason</label>
                <textarea name="reason" rows="3" class="rsl-input mt-1 w-full"></textarea>
            </div>
            <button type="submit" class="rsl-btn-sm">Submit transfer request</button>
        </form>
    </div>
@endsection

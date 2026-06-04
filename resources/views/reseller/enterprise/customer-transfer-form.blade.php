@extends('reseller.layout')

@section('title', 'Customer transfers')

@section('content')
    @include('reseller.partials.page-header', [
        'title' => 'Customer transfers',
        'subtitle' => $customer->name.' ('.$customer->customer_code.')',
        'backUrl' => route('reseller.customer-transfers.index'),
        'backLabel' => '← Transfers',
    ])

    <div class="rsl-panel rsl-panel-pad" style="max-width:28rem">
        <form method="post" action="{{ route('reseller.customer-transfers.store', $customer) }}" class="rsl-form-grid">
            @csrf
            <div class="rsl-field">
                <label class="rsl-field-label" for="to_reseller_id">Transfer to</label>
                <select id="to_reseller_id" name="to_reseller_id" required class="rsl-input">
                    @foreach ($targets as $target)
                        <option value="{{ $target->id }}">{{ $target->name }} ({{ $target->code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="rsl-field">
                <label class="rsl-field-label" for="reason">Reason</label>
                <textarea id="reason" name="reason" rows="3" class="rsl-input"></textarea>
            </div>
            <button type="submit" class="rsl-btn">Submit request</button>
        </form>
    </div>
@endsection

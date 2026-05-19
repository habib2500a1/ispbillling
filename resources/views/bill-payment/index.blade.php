@extends('bill-payment.layout', ['companyName' => $companyName])

@section('title', 'Bill payment')

@section('content')
    <div class="bp-card">
        <h2 class="bp-title">Pay your bill</h2>
        <p class="bp-sub">Provide your client code from the monthly invoice</p>
        @if ($otpEnabled)
            <p class="mt-2 text-xs text-slate-500">A verification code will be sent to your registered mobile.</p>
        @else
            <p class="mt-2 text-xs text-teal-700">No verification code — you will go straight to your bill.</p>
        @endif

        @if ($notification)
            <div class="bp-alert bp-alert-ok">{{ $notification }}</div>
        @endif
        @if (session('portal_disabled'))
            <div class="bp-alert bp-alert-err">Customer portal is currently off. You can still pay your bill here.</div>
        @endif
        @if (session('status'))
            <div class="bp-alert bp-alert-ok">{{ session('status') }}</div>
        @endif
        @if (session('danger'))
            <div class="bp-alert bp-alert-err">{{ session('danger') }}</div>
        @endif
        @if ($errors->any())
            <div class="bp-alert bp-alert-err">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="post" action="{{ route('bill-payment.lookup') }}" class="mt-6">
            @csrf
            <label for="client_code" class="text-sm font-semibold text-slate-700">Client code</label>
            <input
                id="client_code"
                name="client_code"
                type="text"
                class="bp-input"
                placeholder="e.g. 100234"
                value="{{ old('client_code', $prefillCode) }}"
                required
                autofocus
                autocomplete="off"
            >
            <button type="submit" class="bp-btn">Continue to payment</button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            Have a portal account?
            <a href="{{ route('portal.login') }}" class="bp-link">Customer login</a>
        </p>
        <p class="mt-2 text-center text-sm text-slate-500">
            Staff?
            <a href="{{ url('/admin/login') }}" class="bp-link">Admin panel</a>
        </p>
    </div>
@endsection

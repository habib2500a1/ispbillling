@extends('bill-payment.layout', ['companyName' => $companyName])

@section('title', 'Bill payment')

@section('content')
    <div class="bp-card">
        <div class="bp-card-head">
            <span class="bp-card-badge">Secure payment</span>
            <h2 class="bp-title">Pay your bill</h2>
            <p class="bp-sub">Enter your client code from the monthly invoice</p>
        </div>
        @include('partials.demo-credentials-hint', ['demoHint' => 'pay'])
        @if ($otpEnabled)
            <p class="bp-hint">A verification code will be sent to your registered mobile.</p>
        @else
            <p class="bp-hint bp-hint--ok">No verification code — go straight to your bill.</p>
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

        <form method="post" action="{{ route('bill-payment.lookup') }}" class="bp-form">
            @csrf
            <label for="client_code" class="bp-label">Client code</label>
            <input
                id="client_code"
                name="client_code"
                type="text"
                class="bp-input"
                placeholder="e.g. TST0044"
                value="{{ old('client_code', $prefillCode) }}"
                required
                autofocus
                autocapitalize="characters"
                autocomplete="off"
                inputmode="text"
            >
            <button type="submit" class="bp-btn">
                <span>Continue to payment</span>
                <svg class="bp-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </button>
        </form>

        <div class="bp-links">
            <p class="bp-links__row">
                Already have a portal account?
                <a href="{{ route('login.hub') }}" class="bp-link">Sign in</a>
            </p>
            <p class="bp-links__row">
                Staff access?
                <a href="{{ url('/login') }}" class="bp-link">Admin panel</a>
            </p>
        </div>

        <x-mobile-app-promo variant="compact" class="mt-6" />
    </div>
@endsection

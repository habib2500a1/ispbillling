@extends('bill-payment.layout', ['companyName' => $companyName])

@section('title', 'Verify mobile')

@section('content')
    <div class="bp-card">
        <h2 class="bp-title">Verify your number</h2>
        <p class="bp-sub">
            Code sent to <strong>{{ $maskedPhone }}</strong> for client <strong>{{ $customerCode }}</strong>
        </p>

        @if (session('status'))
            <div class="bp-alert bp-alert-ok">{{ session('status') }}</div>
        @endif
        @if (session('danger'))
            <div class="bp-alert bp-alert-err">{{ session('danger') }}</div>
        @endif
        @if ($errors->any())
            <div class="bp-alert bp-alert-err">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('bill-payment.verify.submit') }}" class="mt-6">
            @csrf
            <label for="verification_code" class="text-sm font-semibold text-slate-700">Verification code</label>
            <input
                id="verification_code"
                name="verification_code"
                type="text"
                inputmode="numeric"
                class="bp-input text-center text-2xl tracking-widest"
                placeholder="------"
                maxlength="8"
                required
                autofocus
            >
            <button type="submit" class="bp-btn">Verify &amp; view invoice</button>
        </form>

        <form method="post" action="{{ route('bill-payment.verify.resend') }}" class="mt-3">
            @csrf
            <button type="submit" class="w-full text-center text-sm font-medium text-teal-700 underline">Resend code</button>
        </form>

        <form method="post" action="{{ route('bill-payment.reset') }}" class="mt-4">
            @csrf
            <button type="submit" class="w-full text-center text-sm text-slate-500">Use different client code</button>
        </form>
    </div>
@endsection

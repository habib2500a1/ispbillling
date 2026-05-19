@extends('portal.layout')

@section('title', 'Verify code')

@section('content')
    <div class="mx-auto max-w-md rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
        <h1 class="text-xl font-semibold text-slate-900">Check your email</h1>
        <p class="mt-2 text-sm text-slate-600">
            Enter the one-time code we sent to finish signing in. If you do not see the email, check spam or ask your provider for help.
        </p>

        @if (session('portal_session_expired'))
            <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950 ring-1 ring-amber-200" role="alert">
                Your session expired. <a href="{{ route('portal.login', ['abandon' => 1]) }}" class="font-semibold underline">Sign in again</a>.
            </p>
        @endif

        <form method="post" action="{{ route('portal.login.otp.verify') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="code" class="block text-sm font-medium text-slate-700">One-time code</label>
                <input
                    id="code"
                    name="code"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    autocomplete="one-time-code"
                    required
                    autofocus
                    maxlength="12"
                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm tracking-widest shadow-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                />
            </div>
            <button
                type="submit"
                class="w-full rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white shadow hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2"
            >
                Continue
            </button>
        </form>

        <p class="mt-4 text-center text-sm text-slate-500">
            <a href="{{ route('portal.login', ['abandon' => '1']) }}" class="font-medium text-amber-700 hover:text-amber-800">Start over</a>
        </p>
    </div>
@endsection

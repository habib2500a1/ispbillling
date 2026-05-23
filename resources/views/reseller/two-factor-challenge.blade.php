@extends('reseller.layout')

@section('title', '2FA')

@section('content')
    <div class="rsl-card p-6 max-w-sm mx-auto">
        <h1 class="text-xl font-bold">Two-factor code</h1>
        <form method="post" action="{{ route('reseller.two-factor.verify') }}" class="mt-4">
            @csrf
            <input name="code" inputmode="numeric" autocomplete="one-time-code" required class="w-full rounded-lg border px-3 py-3 text-center text-lg tracking-widest">
            <button type="submit" class="rsl-btn w-full mt-4">Verify</button>
        </form>
    </div>
@endsection

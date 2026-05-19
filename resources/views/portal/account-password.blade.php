@extends('portal.layout')

@section('title', 'Change password')

@section('content')
    <h1 class="text-2xl font-semibold text-slate-900">Change portal password</h1>
    <p class="mt-1 text-sm text-slate-600">Update the password you use to sign in to this portal.</p>

    <form method="post" action="{{ route('portal.account.password.update') }}" class="mt-8 max-w-md space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        <div>
            <label for="current_password" class="block text-sm font-medium text-slate-700">Current password</label>
            <input type="password" name="current_password" id="current_password" required autocomplete="current-password"
                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="password" class="block text-sm font-medium text-slate-700">New password</label>
            <input type="password" name="password" id="password" required autocomplete="new-password"
                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Confirm new password</label>
            <input type="password" name="password_confirmation" id="password_confirmation" required autocomplete="new-password"
                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <button type="submit" class="w-full rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
            Save password
        </button>
    </form>
@endsection

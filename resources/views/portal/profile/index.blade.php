@extends('portal.layout')

@section('title', 'Profile')

@section('content')
    <h1 class="text-2xl font-bold text-indigo-800">Profile management</h1>
    <p class="mt-1 text-sm text-slate-600">Update your contact details and security settings.</p>

    <form method="post" action="{{ route('portal.profile.update') }}" class="mt-8 max-w-lg space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-semibold text-slate-700">Name</label>
            <input type="text" value="{{ $customer->name }}" disabled class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700">Customer code</label>
            <input type="text" value="{{ $customer->customer_code }}" disabled class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-sm">
        </div>
        <div>
            <label for="email" class="block text-sm font-semibold text-slate-700">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $customer->email) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500">
        </div>
        <div>
            <label for="phone" class="block text-sm font-semibold text-slate-700">Phone</label>
            <input id="phone" name="phone" type="text" value="{{ old('phone', $customer->phone) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500">
        </div>
        @if ($customer->package)
            <div>
                <label class="block text-sm font-semibold text-slate-700">Package</label>
                <p class="mt-1 text-sm text-slate-800">{{ $customer->package->name }} — <a href="{{ route('portal.packages.index') }}" class="text-violet-600 hover:underline">Change plan</a></p>
            </div>
        @endif
        @if ($customer->area)
            <div>
                <label class="block text-sm font-semibold text-slate-700">Service area</label>
                <p class="mt-1 text-sm text-slate-800">{{ $customer->area->name }}</p>
            </div>
        @endif
        <button type="submit" class="portal-btn-primary">Save profile</button>
    </form>

    <div class="mt-10 rounded-xl border border-indigo-200 bg-indigo-50/50 p-5">
        <h2 class="font-bold text-indigo-900">Security</h2>
        <p class="mt-1 text-sm text-slate-600">Change your portal login password.</p>
        <a href="{{ route('portal.account.password') }}" class="mt-3 inline-block portal-btn-primary text-sm">Change password</a>
    </div>
@endsection

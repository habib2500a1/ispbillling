@extends('portal.layout')

@section('title', __('portal.signup_title'))

@section('content')
    <div class="mx-auto max-w-lg">
        <h1 class="text-2xl font-bold text-slate-900">{{ __('portal.signup_title') }}</h1>
        <p class="mt-2 text-sm text-slate-600">{{ __('portal.signup_hint') }}</p>

        <form method="post" action="{{ route('portal.signup.store') }}" class="mt-8 space-y-4">
            @csrf
            <div>
                <label for="name" class="block text-sm font-semibold text-slate-700">Full name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required class="mt-1 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
            </div>
            <div>
                <label for="phone" class="block text-sm font-semibold text-slate-700">Mobile number</label>
                <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required class="mt-1 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
            </div>
            <div>
                <label for="email" class="block text-sm font-semibold text-slate-700">Email (optional)</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" class="mt-1 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
            </div>
            <div>
                <label for="package_id" class="block text-sm font-semibold text-slate-700">Preferred package</label>
                <select id="package_id" name="package_id" class="mt-1 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                    <option value="">— Select —</option>
                    @foreach ($packages as $id => $label)
                        <option value="{{ $id }}" @selected(old('package_id') == $id)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="area_id" class="block text-sm font-semibold text-slate-700">Area</label>
                <select id="area_id" name="area_id" class="mt-1 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                    <option value="">— Select —</option>
                    @foreach ($areas as $id => $label)
                        <option value="{{ $id }}" @selected(old('area_id') == $id)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="address" class="block text-sm font-semibold text-slate-700">Address</label>
                <textarea id="address" name="address" rows="2" class="mt-1 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">{{ old('address') }}</textarea>
            </div>
            <button type="submit" class="portal-btn-primary w-full py-3">{{ __('portal.submit_request') }}</button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-600">
            {{ __('portal.already_customer') }} <a href="{{ route('portal.login') }}" class="font-semibold text-violet-600 hover:underline">{{ __('portal.login') }}</a>
        </p>

        <x-mobile-app-promo variant="compact" class="mt-6" />
    </div>
@endsection

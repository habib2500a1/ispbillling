@extends('portal.layout')

@section('title', __('portal.signup_title'))

@section('content')
    <div class="portal-auth-shell portal-auth-shell--split">
        <section class="portal-auth-panel">
            <div class="portal-auth-hero">
                <p class="portal-auth-hero__eyebrow">New connection</p>
                <h1 class="portal-auth-hero__title">{{ __('portal.signup_title') }}</h1>
                <p class="portal-auth-hero__sub">{{ __('portal.signup_hint') }}</p>
            </div>

            <form method="post" action="{{ route('portal.signup.store') }}" class="portal-auth-form">
                @csrf
                <div>
                    <label for="name">Full name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required placeholder="Your full name">
                </div>
                <div>
                    <label for="phone">Mobile number</label>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required placeholder="01XXXXXXXXX">
                </div>
                <div>
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="Optional">
                </div>
                <div class="portal-form-grid portal-form-grid--2">
                    <div>
                        <label for="package_id">Preferred package</label>
                        <select id="package_id" name="package_id">
                            <option value="">Select package</option>
                            @foreach ($packages as $id => $label)
                                <option value="{{ $id }}" @selected(old('package_id') == $id)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="area_id">Area</label>
                        <select id="area_id" name="area_id">
                            <option value="">Select area</option>
                            @foreach ($areas as $id => $label)
                                <option value="{{ $id }}" @selected(old('area_id') == $id)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="3" placeholder="House, road, flat or nearby landmark">{{ old('address') }}</textarea>
                </div>
                <div class="portal-auth-actions">
                    <button type="submit" class="portal-btn-primary w-full py-3">{{ __('portal.submit_request') }}</button>
                </div>
            </form>

            <p class="mt-5 text-center text-sm text-slate-600">
                {{ __('portal.already_customer') }} <a href="{{ route('portal.login') }}" class="portal-link">{{ __('portal.login') }}</a>
            </p>

            <x-mobile-app-promo variant="compact" class="mt-6" />
        </section>

        <aside class="portal-auth-panel">
            <div class="portal-auth-points">
                <div class="portal-auth-point">
                    <p class="portal-auth-point__title">What happens next?</p>
                    <p class="portal-auth-point__body">Your provider reviews the request, checks area availability, and contacts you with installation or activation details.</p>
                </div>
                <div class="portal-auth-point">
                    <p class="portal-auth-point__title">Share accurate contact details</p>
                    <p class="portal-auth-point__body">A correct phone number and area help the ISP team respond faster and match the right package.</p>
                </div>
                <div class="portal-auth-point">
                    <p class="portal-auth-point__title">Need a specific plan?</p>
                    <p class="portal-auth-point__body">Choose your preferred package now, then mention extra requirements when the provider contacts you.</p>
                </div>
            </div>
        </aside>
    </div>
@endsection

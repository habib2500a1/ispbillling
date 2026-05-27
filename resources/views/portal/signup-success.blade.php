@extends('portal.layout')

@section('title', 'Request received')

@section('content')
    <div class="portal-auth-shell">
        <section class="portal-auth-panel text-center">
            <span class="portal-success-badge">✓</span>
            <h1 class="portal-auth-hero__title mt-5">Thank you</h1>
            <p class="portal-auth-hero__sub mt-3">{{ session('status', 'Your request was received.') }}</p>

            <div class="portal-auth-points mt-6 text-left">
                <div class="portal-auth-point">
                    <p class="portal-auth-point__title">Request submitted</p>
                    <p class="portal-auth-point__body">Your connection request is now in the provider queue for review.</p>
                </div>
                <div class="portal-auth-point">
                    <p class="portal-auth-point__title">Next step</p>
                    <p class="portal-auth-point__body">The ISP team may contact you for coverage, package confirmation, or installation scheduling.</p>
                </div>
            </div>

            <div class="portal-auth-actions mt-6 justify-center">
                <a href="{{ route('portal.login') }}" class="portal-btn-primary px-8 py-3">Back to login</a>
                @if (config('portal.signup.enabled', true))
                    <a href="{{ route('portal.signup') }}" class="portal-card-button">Submit another request</a>
                @endif
            </div>
        </section>
    </div>
@endsection

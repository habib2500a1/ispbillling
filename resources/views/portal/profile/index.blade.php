@extends('portal.layout')

@section('title', 'Profile')

@section('content')
    <div class="portal-page-head">
        <div>
            <h1 class="portal-page-title">Profile management</h1>
            <p class="portal-page-lead">Review account identity, update contact information, and manage portal security from one place.</p>
        </div>
        <a href="{{ route('portal.account.password') }}" class="portal-card-button">Change password</a>
    </div>

    <div class="portal-summary-grid">
        <article class="portal-summary-card portal-summary-card--info">
            <p class="portal-summary-card__eyebrow">Customer ID</p>
            <p class="portal-summary-card__value">{{ $customer->customer_code }}</p>
            <p class="portal-summary-card__meta">Use this code when talking to support or checking service requests.</p>
        </article>
        <article class="portal-summary-card {{ $customer->package ? 'portal-summary-card--ok' : 'portal-summary-card--warn' }}">
            <p class="portal-summary-card__eyebrow">Current package</p>
            <p class="portal-summary-card__value">{{ $customer->package?->name ?? 'Not assigned' }}</p>
            <p class="portal-summary-card__meta">
                @if ($customer->package)
                    {{ $customer->package->download_mbps }} Mbps plan · <a href="{{ route('portal.packages.index') }}" class="portal-link">change plan</a>
                @else
                    Contact support if your plan information is missing.
                @endif
            </p>
        </article>
    </div>

    <div class="portal-section-grid portal-section-grid--2">
        <section class="portal-surface-card">
            <div class="portal-section-head">
                <div class="portal-label-stack">
                    <h2 class="portal-surface-card__title">Account details</h2>
                    <p class="portal-surface-card__meta">Primary account identity and service metadata for this portal login.</p>
                </div>
            </div>

            <div class="portal-info-grid portal-info-grid--2">
                <div class="portal-field-card">
                    <p class="portal-field-card__label">Name</p>
                    <p class="portal-field-card__value">{{ $customer->name }}</p>
                </div>
                <div class="portal-field-card">
                    <p class="portal-field-card__label">Customer code</p>
                    <p class="portal-field-card__value portal-mono">{{ $customer->customer_code }}</p>
                </div>
                @if ($customer->package)
                    <div class="portal-field-card">
                        <p class="portal-field-card__label">Package</p>
                        <p class="portal-field-card__value">{{ $customer->package->name }}</p>
                    </div>
                @endif
                @if ($customer->area)
                    <div class="portal-field-card">
                        <p class="portal-field-card__label">Service area</p>
                        <p class="portal-field-card__value">{{ $customer->area->name }}</p>
                    </div>
                @endif
            </div>
        </section>

        <section class="portal-surface-card">
            <div class="portal-section-head">
                <div class="portal-label-stack">
                    <h2 class="portal-surface-card__title">Security</h2>
                    <p class="portal-surface-card__meta">Keep your customer portal login protected with a strong password.</p>
                </div>
            </div>

            <div class="portal-note-banner">
                Update your password if you shared it with someone, used it on another website, or noticed unexpected account activity.
            </div>

            <ul class="portal-note-list">
                <li>Use a unique password for your ISP portal.</li>
                <li>Avoid sharing portal credentials over chat or phone.</li>
                <li>Change password immediately if you suspect misuse.</li>
            </ul>

            <div class="portal-form-actions">
                <a href="{{ route('portal.account.password') }}" class="portal-btn-primary">Go to password settings</a>
            </div>
        </section>
    </div>

    <section class="portal-surface-card">
        <div class="portal-section-head">
            <div class="portal-label-stack">
                <h2 class="portal-surface-card__title">Contact information</h2>
                <p class="portal-surface-card__meta">Keep your phone and email updated so payment, outage, and support notices reach you correctly.</p>
            </div>
        </div>

        <form method="post" action="{{ route('portal.profile.update') }}" class="portal-form-grid">
            @csrf
            <div class="portal-form-grid portal-form-grid--2">
                <div>
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $customer->email) }}" placeholder="name@example.com">
                </div>
                <div>
                    <label for="phone">Phone</label>
                    <input id="phone" name="phone" type="text" value="{{ old('phone', $customer->phone) }}" placeholder="01XXXXXXXXX">
                </div>
            </div>
            <div class="portal-form-actions">
                <button type="submit" class="portal-btn-primary">Save profile</button>
            </div>
        </form>
    </section>

    @if (($movieServers ?? collect())->isNotEmpty())
        <div class="portal-media-strip">
            <x-movie-servers-showcase
                :servers="$movieServers"
                variant="portal"
                title="Entertainment & FTP servers"
                subtitle="Open or copy server links for movies, FTP libraries, and streaming access included with your plan."
            />
        </div>
    @endif
@endsection

@extends('portal.layout')

@section('title', 'Change password')

@section('content')
    <div class="portal-page-head">
        <div>
            <h1 class="portal-page-title">Change portal password</h1>
            <p class="portal-page-lead">Update the password you use to sign in to this customer portal.</p>
        </div>
        <a href="{{ route('portal.profile.index') }}" class="portal-card-button">Back to profile</a>
    </div>

    <div class="portal-section-grid portal-section-grid--2">
        <section class="portal-surface-card">
            <div class="portal-section-head">
                <div class="portal-label-stack">
                    <h2 class="portal-surface-card__title">Password update</h2>
                    <p class="portal-surface-card__meta">Enter your current password, then choose a stronger replacement.</p>
                </div>
            </div>

            <form method="post" action="{{ route('portal.account.password.update') }}" class="portal-form-grid">
                @csrf
                <div>
                    <label for="current_password">Current password</label>
                    <input type="password" name="current_password" id="current_password" required autocomplete="current-password">
                </div>
                <div>
                    <label for="password">New password</label>
                    <input type="password" name="password" id="password" required autocomplete="new-password">
                </div>
                <div>
                    <label for="password_confirmation">Confirm new password</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" required autocomplete="new-password">
                </div>
                <div class="portal-form-actions">
                    <button type="submit" class="portal-btn-primary">Save password</button>
                </div>
            </form>
        </section>

        <section class="portal-surface-card">
            <div class="portal-section-head">
                <div class="portal-label-stack">
                    <h2 class="portal-surface-card__title">Security tips</h2>
                    <p class="portal-surface-card__meta">A few simple habits help keep your customer account secure.</p>
                </div>
            </div>

            <div class="portal-note-banner">
                Choose a password that is hard to guess and not reused on your email, social, or banking accounts.
            </div>

            <ul class="portal-note-list">
                <li>Use at least 6 characters, but longer is better.</li>
                <li>Mix letters, numbers, and symbols when possible.</li>
                <li>Do not share your password with agents over phone or chat.</li>
                <li>Change it immediately if you suspect unauthorized access.</li>
            </ul>
        </section>
    </div>
@endsection

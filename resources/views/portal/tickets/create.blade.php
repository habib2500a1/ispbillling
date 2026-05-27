@extends('portal.layout')

@section('title', 'New support ticket')

@section('content')
    <div class="portal-page-head">
        <div>
            <h1 class="portal-page-title">New support ticket</h1>
            <p class="portal-page-lead">Share the issue clearly so the support team can route it fast and respond with the right context.</p>
        </div>
        <a href="{{ route('portal.tickets.index') }}" class="portal-card-button">All tickets</a>
    </div>

    <div class="portal-summary-grid">
        <article class="portal-summary-card portal-summary-card--info">
            <p class="portal-summary-card__eyebrow">Best results</p>
            <p class="portal-summary-card__value">Clear subject</p>
            <p class="portal-summary-card__meta">Mention package, ONU, bill, payment, or speed issue directly in the title.</p>
        </article>
        <article class="portal-summary-card portal-summary-card--warn">
            <p class="portal-summary-card__eyebrow">Attach proof</p>
            <p class="portal-summary-card__value">Image or PDF</p>
            <p class="portal-summary-card__meta">Screenshots, payment slip, or ONU light photo usually speeds up resolution.</p>
        </article>
    </div>

    <section class="portal-surface-card">
        <div class="portal-section-head">
            <div class="portal-label-stack">
                <h2 class="portal-surface-card__title">Describe your issue</h2>
                <p class="portal-surface-card__meta">Fill the details below and the ticket will be created immediately in your account.</p>
            </div>
            <span class="portal-inline-chip">Response depends on department and priority</span>
        </div>

        <form method="post" action="{{ route('portal.tickets.store') }}" enctype="multipart/form-data" class="portal-form-grid">
            @csrf

            <div>
                <label for="subject">Subject</label>
                <input type="text" name="subject" id="subject" value="{{ old('subject') }}" required maxlength="255" placeholder="Example: Internet down since morning">
            </div>

            <div class="portal-form-grid portal-form-grid--2">
                <div>
                    <label for="department">Department</label>
                    <select name="department" id="department" required>
                        @foreach ($departments as $value => $label)
                            <option value="{{ $value }}" @selected(old('department') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="priority">Priority</label>
                    <select name="priority" id="priority" required>
                        @foreach ($priorities as $value => $label)
                            <option value="{{ $value }}" @selected(old('priority', 'medium') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label for="issue_type">Issue type</label>
                <select name="issue_type" id="issue_type">
                    <option value="">Select if relevant</option>
                    @foreach ($issueTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('issue_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="description">Describe the problem</label>
                <textarea name="description" id="description" rows="7" required maxlength="10000" placeholder="When did it start, what have you already tried, and which device or connection is affected?">{{ old('description') }}</textarea>
            </div>

            <div>
                <label for="attachment">Photo or PDF</label>
                <input type="file" name="attachment" id="attachment" accept="image/*,.pdf">
                <p class="portal-surface-card__meta">Optional, but useful for screenshots, payment slips, router status, or ONU signal photos.</p>
            </div>

            <div class="portal-note-banner">
                For faster support, include your exact issue time, affected room or router, and whether the problem is billing, speed, connection loss, or optical signal related.
            </div>

            <div class="portal-form-actions">
                <button type="submit" class="portal-btn-primary portal-btn-ticket">Submit ticket</button>
                <a href="{{ route('portal.tickets.index') }}" class="portal-card-button">Cancel</a>
            </div>
        </form>
    </section>
@endsection

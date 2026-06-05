@extends('portal.layout')

@section('title', 'Speed test')

@section('content')
    <div class="portal-speedtest-page">
        <div class="portal-page-head portal-page-head--stack">
            <div>
                <h1 class="portal-page-title">Internet speed test</h1>
                <p class="portal-page-lead">Tap <strong>START</strong> to measure real download, upload, and latency.</p>
            </div>
        </div>

        @include('portal.partials.speed-test-widget', ['speedtest' => $speedtest])

        <section class="portal-speedtest-backend" aria-label="Your connection usage">
            <header class="portal-speedtest-backend__head">
                <div>
                    <h2 class="portal-speedtest-backend__title">Your connection usage</h2>
                    <p class="portal-speedtest-backend__lead">
                        Live stats from {{ $companyName ?? config('app.name') }} — router sync.
                    </p>
                </div>
                <a href="{{ route('portal.usage.index') }}" class="portal-card-button">Full usage page →</a>
            </header>

            @include('portal.partials.usage-stats-strip', ['stats' => $stats, 'pollSeconds' => $pollSeconds])
        </section>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
        <script src="{{ asset('js/portal-usage.js') }}?v=6" defer></script>
        <script src="{{ asset('js/portal-speedtest-live.js') }}?v={{ @filemtime(public_path('js/portal-speedtest-live.js')) ?: 1 }}" defer></script>
    @endpush
@endsection

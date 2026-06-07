@if (\App\Support\DemoMode::enabled() && auth()->check() && ! request()->routeIs('filament.admin.auth.*'))
    @include('partials.demo-banner')
@endif

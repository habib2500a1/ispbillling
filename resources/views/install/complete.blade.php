@extends('install.layout')

@section('content')
    <h1>Installation Complete</h1>
    <p class="lead">সব setup শেষ। এখন admin panel এ login করুন।</p>

    <div class="alert ok">
        Admin URL: <a href="{{ $adminUrl }}">{{ $adminUrl }}</a><br>
        Email: <code>{{ $adminEmail }}</code>
    </div>

    <div class="alert info">
        <strong>Mobile APK</strong> — background এ আপনার domain (<code>{{ rtrim(config('app.url'), '/') }}</code>) দিয়ে auto build হচ্ছে।
        কিছুক্ষণ পর: <a href="{{ rtrim(config('app.url'), '/') }}/downloads/isp-radiant.apk">downloads/isp-radiant.apk</a>
    </div>

    <div class="alert info">
        <strong>cPanel Cron (অবশ্যই add করুন):</strong><br>
        <code>cd {{ base_path() }} && php artisan schedule:run >> /dev/null 2>&1</code><br>
        <code>cd {{ base_path() }} && php artisan queue:work database --stop-when-empty --max-time=55 >> storage/logs/queue.log 2>&1</code>
    </div>

    <a class="btn" href="{{ $adminUrl }}">Open Admin Panel</a>
@endsection

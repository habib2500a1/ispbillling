@extends('install.layout')

@section('content')
    <h1>ISP Platform Setup</h1>
    <p class="lead">Zip unzip করার পর domain point করলে এই wizard automatically চালু হবে। Step by step permission, database, admin setup হবে।</p>

    <div class="alert info">
        Document root: <code>{{ $documentRoot }}</code><br>
        Laravel root: <code>{{ $laravelRoot }}</code>
    </div>

    <table>
        <thead>
        <tr><th>Check</th><th>Status</th><th>Note</th></tr>
        </thead>
        <tbody>
        @foreach($requirements['checks'] as $check)
            <tr>
                <td>{{ $check['label'] }}</td>
                <td class="{{ $check['ok'] ? 'ok' : 'bad' }}">{{ $check['ok'] ? 'OK' : 'FAIL' }}</td>
                <td>{{ $check['hint'] ?? '' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    @if($requirements['ok'])
        <a class="btn" href="{{ route('install.permissions') }}">Next: Permissions →</a>
    @else
        <p class="errors">Requirements ঠিক করুন (PHP 8.2+, extensions, vendor/) তারপর refresh করুন।</p>
        <a class="btn secondary" href="{{ route('install.welcome') }}">Refresh</a>
    @endif
@endsection

@extends('install.layout')

@section('content')
    <h1>Step 1 — Permissions</h1>
    <p class="lead">storage/ এবং bootstrap/cache/ writable হতে হবে। নিচের button চাপলে auto-fix চেষ্টা করবে।</p>

    <table>
        <thead>
        <tr><th>Folder</th><th>Writable</th></tr>
        </thead>
        <tbody>
        @foreach($permissions as $row)
            <tr>
                <td><code>{{ $row['path'] }}</code></td>
                <td class="{{ $row['ok'] ? 'ok' : 'bad' }}">{{ $row['ok'] ? 'Yes' : 'No' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <form method="post" action="{{ route('install.permissions.fix') }}">
        @csrf
        <button class="btn secondary" type="submit">Fix permissions</button>
    </form>

    @if($permissionsOk)
        <a class="btn" href="{{ route('install.database') }}">Next: Database →</a>
    @else
        <p class="errors">cPanel File Manager → folder permission 755/775 দিন, অথবা support কে বলুন web user কে storage access দিতে।</p>
    @endif
@endsection

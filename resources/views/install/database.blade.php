@extends('install.layout')

@section('content')
    <h1>Step 2 — Database</h1>
    <p class="lead">cPanel → MySQL Databases থেকে database + user তৈরি করে নিচে দিন।</p>

    <form method="post" action="{{ route('install.database.store') }}">
        @csrf
        <label for="db_driver">Database type</label>
        <select id="db_driver" name="db_driver">
            <option value="mysql" @selected(old('db_driver', $defaults['db_driver']) === 'mysql')>MySQL (cPanel)</option>
            <option value="pgsql" @selected(old('db_driver', $defaults['db_driver']) === 'pgsql')>PostgreSQL</option>
        </select>

        <div class="row">
            <div>
                <label for="db_host">Host</label>
                <input id="db_host" name="db_host" value="{{ old('db_host', $defaults['db_host']) }}" required>
            </div>
            <div>
                <label for="db_port">Port</label>
                <input id="db_port" name="db_port" value="{{ old('db_port', $defaults['db_port']) }}">
            </div>
        </div>

        <label for="db_database">Database name</label>
        <input id="db_database" name="db_database" value="{{ old('db_database') }}" required>

        <label for="db_username">Username</label>
        <input id="db_username" name="db_username" value="{{ old('db_username') }}" required>

        <label for="db_password">Password</label>
        <input id="db_password" name="db_password" type="password" value="{{ old('db_password') }}">

        @if ($errors->any())
            <div class="errors">{{ $errors->first() }}</div>
        @endif

        <button class="btn" type="submit">Test &amp; Continue →</button>
    </form>
@endsection

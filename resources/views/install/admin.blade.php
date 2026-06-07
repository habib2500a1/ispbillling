@extends('install.layout')

@section('content')
    <h1>Step 3 — Site &amp; Admin</h1>
    <p class="lead">Domain URL, company name, এবং প্রথম admin login এখানে সেট হবে।</p>

    <form method="post" action="{{ route('install.admin.store') }}">
        @csrf
        <label for="app_url">Site URL (https://...)</label>
        <input id="app_url" name="app_url" value="{{ old('app_url', $defaults['app_url']) }}" required>

        <div class="row">
            <div>
                <label for="app_name">App name</label>
                <input id="app_name" name="app_name" value="{{ old('app_name', $defaults['app_name']) }}" required>
            </div>
            <div>
                <label for="company_name">Company name</label>
                <input id="company_name" name="company_name" value="{{ old('company_name', $defaults['company_name']) }}" required>
            </div>
        </div>

        <label for="admin_email">Admin email</label>
        <input id="admin_email" name="admin_email" type="email" value="{{ old('admin_email') }}" required>

        <label for="admin_password">Admin password (min 8 chars)</label>
        <input id="admin_password" name="admin_password" type="password" required>

        @if ($errors->any())
            <div class="errors">{{ $errors->first() }}</div>
        @endif

        <button class="btn" type="submit">Install now</button>
    </form>
@endsection

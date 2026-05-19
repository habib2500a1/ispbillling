<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reseller login — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/reseller-portal.css') }}">
</head>
<body class="rsl-bg flex min-h-screen items-center justify-center px-4">
    <div class="rsl-card w-full max-w-md p-8">
        <div class="mb-6 text-center">
            <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-2xl font-bold text-white">R</span>
            <h1 class="mt-4 text-2xl font-bold text-slate-900">Reseller portal</h1>
            <p class="mt-1 text-sm text-slate-600">Sign in with partner code, email, or phone</p>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="post" action="{{ route('reseller.login.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="login">Partner ID</label>
                <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus class="rsl-input" placeholder="RSL-2605-0001 or email">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="password">Password</label>
                <input id="password" name="password" type="password" required class="rsl-input">
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" value="1" class="rounded border-slate-300">
                Remember me
            </label>
            <button type="submit" class="rsl-btn w-full">Sign in</button>
        </form>
    </div>
</body>
</html>

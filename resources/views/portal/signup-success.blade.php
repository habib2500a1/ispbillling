@extends('portal.layout')

@section('title', 'Request received')

@section('content')
    <div class="mx-auto max-w-md text-center">
        <span class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-3xl text-emerald-600">✓</span>
        <h1 class="mt-4 text-2xl font-bold text-slate-900">Thank you</h1>
        <p class="mt-2 text-sm text-slate-600">{{ session('status', 'Your request was received.') }}</p>
        <a href="{{ route('portal.login') }}" class="portal-btn-primary mt-8 inline-block px-8 py-3">Back to login</a>
    </div>
@endsection

@extends('portal.layout')

@section('title', $article->title)

@section('content')
    <div class="mb-4">
        <a href="{{ route('portal.kb.index') }}" class="text-sm font-medium text-amber-800 hover:underline">← All articles</a>
    </div>
    <article class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm prose prose-slate max-w-none">
        <h1 class="text-2xl font-semibold text-slate-900">{{ $article->title }}</h1>
        @if ($article->published_at)
            <p class="text-sm text-slate-500 not-prose">{{ $article->published_at->format('F j, Y') }}</p>
        @endif
        <div class="mt-6">
            {!! $article->body !!}
        </div>
    </article>
@endsection

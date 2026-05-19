@extends('portal.layout')

@section('title', 'Help articles')

@section('content')
    <h1 class="mb-6 text-2xl font-semibold text-slate-900">Help &amp; knowledge base</h1>

    @if ($articles->isEmpty())
        <p class="rounded-lg border border-slate-200 bg-white p-6 text-slate-600">No published articles yet.</p>
    @else
        <ul class="divide-y divide-slate-200 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            @foreach ($articles as $article)
                <li>
                    <a href="{{ route('portal.kb.show', $article->slug) }}" class="block px-4 py-4 hover:bg-amber-50">
                        <span class="font-semibold text-amber-900">{{ $article->title }}</span>
                        @if ($article->published_at)
                            <span class="mt-1 block text-xs text-slate-500">{{ $article->published_at->format('M j, Y') }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $articles->links() }}</div>
    @endif
@endsection

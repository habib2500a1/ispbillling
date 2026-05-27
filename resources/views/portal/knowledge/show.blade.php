@extends('portal.layout')

@section('title', $article->title)

@section('content')
    <div class="portal-page-head">
        <div>
            <a href="{{ route('portal.kb.index') }}" class="portal-link">← All articles</a>
            <h1 class="portal-page-title mt-3">{{ $article->title }}</h1>
            <p class="portal-page-lead">
                @if ($article->published_at)
                    Published {{ $article->published_at->format('F j, Y') }}
                @else
                    Published date not available
                @endif
            </p>
        </div>
        <a href="{{ route('portal.tickets.create') }}" class="portal-card-button">Still need help?</a>
    </div>

    <div class="portal-article-grid portal-article-grid--2">
        <article class="portal-surface-card">
            <div class="portal-prose">
                {!! $article->body !!}
            </div>
        </article>

        <aside class="portal-surface-card">
            <div class="portal-section-head">
                <div class="portal-label-stack">
                    <h2 class="portal-surface-card__title">Related help</h2>
                    <p class="portal-surface-card__meta">More articles and next support options if this guide does not fully solve the issue.</p>
                </div>
            </div>

            @if (($relatedArticles ?? collect())->isNotEmpty())
                <div class="portal-article-list">
                    @foreach ($relatedArticles as $related)
                        <a href="{{ route('portal.kb.show', $related->slug) }}" class="portal-article-card">
                            <h3 class="portal-article-card__title">{{ $related->title }}</h3>
                            <p class="portal-article-card__meta">{{ $related->published_at?->format('M j, Y') ?? 'Published date unavailable' }}</p>
                            <p class="portal-article-card__excerpt">{{ $related->excerpt }}</p>
                        </a>
                    @endforeach
                </div>
            @else
                <p class="portal-empty-state">No related articles found yet for this topic.</p>
            @endif

            <div class="portal-chat-history">
                <div class="portal-chat-history__item">
                    <p class="portal-chat-history__title">Need a human reply?</p>
                    <p class="portal-chat-history__meta">Open a support ticket if the article does not match your exact billing, connection, or ONU issue.</p>
                    <a href="{{ route('portal.tickets.create') }}" class="portal-pro-card__link">Open support ticket →</a>
                </div>
                <div class="portal-chat-history__item">
                    <p class="portal-chat-history__title">Want faster back-and-forth?</p>
                    <p class="portal-chat-history__meta">Use live chat to open a support conversation that continues inside the same ticket thread.</p>
                    <a href="{{ route('portal.live-chat') }}" class="portal-pro-card__link">Open live chat →</a>
                </div>
            </div>
        </aside>
    </div>
@endsection

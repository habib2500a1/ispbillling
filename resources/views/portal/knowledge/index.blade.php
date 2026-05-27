@extends('portal.layout')

@section('title', 'Help articles')

@section('content')
    <div class="portal-page-head">
        <div>
            <h1 class="portal-page-title">Help &amp; knowledge base</h1>
            <p class="portal-page-lead">Find quick answers for bills, speed, router issues, ONU signal, and common account questions.</p>
        </div>
        <a href="{{ route('portal.tickets.create') }}" class="portal-card-button">Need support?</a>
    </div>

    <div class="portal-summary-grid">
        <article class="portal-summary-card portal-summary-card--info">
            <p class="portal-summary-card__eyebrow">Published articles</p>
            <p class="portal-summary-card__value">{{ $articleCount ?? $articles->total() }}</p>
            <p class="portal-summary-card__meta">Guides and troubleshooting notes available for your ISP tenant.</p>
        </article>
        <article class="portal-summary-card {{ $latestArticle ? 'portal-summary-card--ok' : 'portal-summary-card--warn' }}">
            <p class="portal-summary-card__eyebrow">Latest article</p>
            <p class="portal-summary-card__value">{{ $latestArticle?->published_at?->format('d M Y') ?? 'Not available' }}</p>
            <p class="portal-summary-card__meta">{{ $latestArticle?->title ?? 'No published help article yet.' }}</p>
        </article>
    </div>

    @if ($articles->isEmpty())
        <section class="portal-surface-card">
            <p class="portal-empty-state">No published articles yet. You can open a support ticket if you need direct help.</p>
            <div class="portal-form-actions">
                <a href="{{ route('portal.tickets.create') }}" class="portal-btn-primary portal-btn-ticket">Open support ticket</a>
            </div>
        </section>
    @else
        <div class="portal-article-grid portal-article-grid--2">
            <section class="portal-surface-card">
                <div class="portal-section-head">
                    <div class="portal-label-stack">
                        <h2 class="portal-surface-card__title">All articles</h2>
                        <p class="portal-surface-card__meta">Browse recent help content and open the guide that matches your issue.</p>
                    </div>
                </div>

                <div class="portal-article-list">
                    @foreach ($articles as $article)
                        <a href="{{ route('portal.kb.show', $article->slug) }}" class="portal-article-card">
                            <h3 class="portal-article-card__title">{{ $article->title }}</h3>
                            <p class="portal-article-card__meta">
                                @if ($article->published_at)
                                    Published {{ $article->published_at->format('M j, Y') }}
                                @else
                                    Published date not available
                                @endif
                            </p>
                            <p class="portal-article-card__excerpt">{{ \Illuminate\Support\Str::limit(trim(strip_tags((string) $article->body)), 160) }}</p>
                        </a>
                    @endforeach
                </div>

                <div class="mt-4">{{ $articles->links() }}</div>
            </section>

            <aside class="portal-surface-card">
                <div class="portal-section-head">
                    <div class="portal-label-stack">
                        <h2 class="portal-surface-card__title">Popular support paths</h2>
                        <p class="portal-surface-card__meta">If the article does not solve it, jump to the right next action.</p>
                    </div>
                </div>

                <div class="portal-chat-history">
                    <div class="portal-chat-history__item">
                        <p class="portal-chat-history__title">Billing or due issue</p>
                        <p class="portal-chat-history__meta">Check your due details, then open a billing ticket if something looks incorrect.</p>
                        <a href="{{ route('portal.bills.index') }}" class="portal-pro-card__link">View bills →</a>
                    </div>
                    <div class="portal-chat-history__item">
                        <p class="portal-chat-history__title">Speed or latency problem</p>
                        <p class="portal-chat-history__meta">Run a speed test first so you can share measurable results with support.</p>
                        <a href="{{ route('portal.speed-test.index') }}" class="portal-pro-card__link">Run speed test →</a>
                    </div>
                    <div class="portal-chat-history__item">
                        <p class="portal-chat-history__title">Optical / ONU warning</p>
                        <p class="portal-chat-history__meta">Review live optical status before creating an ONU or field support request.</p>
                        <a href="{{ route('portal.onu.index') }}" class="portal-pro-card__link">Open ONU status →</a>
                    </div>
                </div>
            </aside>
        </div>
    @endif
@endsection

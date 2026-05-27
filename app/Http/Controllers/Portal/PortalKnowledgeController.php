<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeArticle;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PortalKnowledgeController extends Controller
{
    public function index(): View
    {
        $customer = auth('customer')->user();
        $baseQuery = KnowledgeArticle::query()
            ->publishedForTenant((int) $customer->tenant_id)
            ->orderByDesc('published_at');

        $articles = (clone $baseQuery)
            ->paginate(20);

        $articleCount = (clone $baseQuery)->count();
        $latestArticle = (clone $baseQuery)->first();

        return view('portal.knowledge.index', [
            'articles' => $articles,
            'articleCount' => $articleCount,
            'latestArticle' => $latestArticle,
        ]);
    }

    public function show(string $slug): View
    {
        $customer = auth('customer')->user();
        $article = KnowledgeArticle::query()
            ->publishedForTenant((int) $customer->tenant_id)
            ->where('slug', $slug)
            ->firstOrFail();

        $relatedArticles = KnowledgeArticle::query()
            ->publishedForTenant((int) $customer->tenant_id)
            ->whereKeyNot($article->getKey())
            ->orderByDesc('published_at')
            ->limit(4)
            ->get()
            ->map(function (KnowledgeArticle $related): KnowledgeArticle {
                $related->excerpt = Str::limit(trim(strip_tags((string) $related->body)), 120);

                return $related;
            });

        return view('portal.knowledge.show', [
            'article' => $article,
            'relatedArticles' => $relatedArticles,
        ]);
    }
}

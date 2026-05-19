<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeArticle;
use Illuminate\View\View;

class PortalKnowledgeController extends Controller
{
    public function index(): View
    {
        $customer = auth('customer')->user();
        $articles = KnowledgeArticle::query()
            ->publishedForTenant((int) $customer->tenant_id)
            ->orderByDesc('published_at')
            ->paginate(20);

        return view('portal.knowledge.index', [
            'articles' => $articles,
        ]);
    }

    public function show(string $slug): View
    {
        $customer = auth('customer')->user();
        $article = KnowledgeArticle::query()
            ->publishedForTenant((int) $customer->tenant_id)
            ->where('slug', $slug)
            ->firstOrFail();

        return view('portal.knowledge.show', [
            'article' => $article,
        ]);
    }
}

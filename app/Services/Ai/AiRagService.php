<?php

namespace App\Services\Ai;

use App\Models\AiKnowledgeDocument;
use App\Support\TenantResolver;

final class AiRagService
{
    public function __construct(
        private readonly AiSettingsService $settings,
    ) {}

    /**
     * @return list<array{title: string, excerpt: string, category: string}>
     */
    public function search(string $query, ?int $tenantId = null, int $limit = 3): array
    {
        if (! $this->settings->ragEnabled($tenantId)) {
            return [];
        }

        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $locale = preg_match('/[\x{0980}-\x{09FF}]/u', $query) ? 'bn' : 'en';
        $terms = array_values(array_filter(preg_split('/\s+/u', mb_strtolower(trim($query))) ?: []));

        if ($terms === []) {
            return [];
        }

        $docs = AiKnowledgeDocument::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('locale', [$locale, 'en', 'bn'])
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        $scored = [];
        foreach ($docs as $doc) {
            $hay = mb_strtolower($doc->title.' '.$doc->content);
            $score = 0;
            foreach ($terms as $term) {
                if (mb_strlen($term) < 3) {
                    continue;
                }
                if (str_contains($hay, $term)) {
                    $score++;
                }
            }
            if ($score > 0) {
                $scored[] = ['score' => $score, 'doc' => $doc];
            }
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice(array_map(function (array $row): array {
            /** @var AiKnowledgeDocument $doc */
            $doc = $row['doc'];

            return [
                'title' => $doc->title,
                'excerpt' => mb_substr(trim($doc->content), 0, 280),
                'category' => $doc->category,
            ];
        }, $scored), 0, $limit);
    }

    public function contextBlock(string $query, ?int $tenantId = null): string
    {
        $hits = $this->search($query, $tenantId);
        if ($hits === []) {
            return '';
        }

        $lines = array_map(
            fn (array $h): string => '- '.$h['title'].': '.$h['excerpt'],
            $hits,
        );

        return "Knowledge base:\n".implode("\n", $lines);
    }
}

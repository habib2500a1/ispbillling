<?php

namespace App\Services\Ai;

/**
 * Formats read-only tool output for conversational UI.
 */
final class AiInsightComposer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{reply: string, cards: list<array<string, mixed>>, table: ?array, links: list<array{label: string, url: string}>}
     */
    public function compose(string $tool, array $payload): array
    {
        $summary = (string) ($payload['summary'] ?? 'Here are the results.');
        $cards = is_array($payload['cards'] ?? null) ? $payload['cards'] : [];
        $table = is_array($payload['table'] ?? null) ? $payload['table'] : null;
        $links = is_array($payload['links'] ?? null) ? $payload['links'] : [];

        return [
            'reply' => $summary,
            'cards' => $cards,
            'table' => $table,
            'links' => $links,
            'tool' => $tool,
            'domain' => (string) ($payload['domain'] ?? 'general'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{headers: list<string>, rows: list<list<string>>}
     */
    public function table(array $headers, array $rows): array
    {
        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }
}

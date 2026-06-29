<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class AiLlmGateway
{
    public function __construct(
        private readonly AiSettingsService $settings,
        private readonly AiIntentCatalog $intents,
    ) {}

    /**
     * Resolve tool name from natural language using LLM, or null to fall back to rules.
     */
    public function resolveTool(string $query, ?int $tenantId = null): ?string
    {
        if (! $this->settings->llmEnabled($tenantId) || ! $this->hasApiKey($tenantId)) {
            return null;
        }

        $tools = array_map(
            fn (array $def): string => $def['tool'].' — '.implode(', ', array_slice($def['patterns'], 0, 3)),
            $this->intents->definitions(),
        );

        $system = 'You are an ISP operations assistant. Pick exactly one tool id from the list that best matches the user question. Reply with JSON only: {"tool":"billing.due_customers"} or {"tool":null} if none match.';
        $user = "Tools:\n".implode("\n", $tools)."\n\nQuestion: ".$query;

        $content = $this->chat($system, $user, $tenantId);
        if ($content === null) {
            return null;
        }

        $json = $this->extractJson($content);
        $tool = is_array($json) ? ($json['tool'] ?? null) : null;

        return is_string($tool) && $tool !== '' && $this->settings->toolAllowed($tool, $tenantId) ? $tool : null;
    }

    /**
     * Optional natural-language summary over structured tool output.
     *
     * @param  array<string, mixed>  $payload
     */
    public function summarizeToolResult(string $tool, string $query, array $payload, ?int $tenantId = null): ?string
    {
        if (! $this->settings->llmEnabled($tenantId) || ! $this->hasApiKey($tenantId)) {
            return null;
        }

        $lang = $this->settings->bengaliReplies($tenantId) && preg_match('/[\x{0980}-\x{09FF}]/u', $query)
            ? 'Bengali'
            : 'English';

        $system = "Summarize ISP operations data in {$lang}. Be concise (2-4 sentences). Advisory only — never claim you executed changes.";
        $user = 'Tool: '.$tool."\nData: ".json_encode($payload, JSON_UNESCAPED_UNICODE);

        return $this->chat($system, $user, $tenantId);
    }

    private function hasApiKey(?int $tenantId): bool
    {
        return filled($this->settings->get('llm.api_key', config('ai.llm.api_key'), $tenantId));
    }

    private function chat(string $system, string $user, ?int $tenantId): ?string
    {
        $apiKey = (string) $this->settings->get('llm.api_key', config('ai.llm.api_key'), $tenantId);
        $baseUrl = rtrim((string) $this->settings->get('llm.base_url', config('ai.llm.base_url'), $tenantId), '/');
        $model = (string) $this->settings->get('llm.model', config('ai.llm.model'), $tenantId);
        $timeout = (int) $this->settings->get('llm.timeout', config('ai.llm.timeout', 30), $tenantId);

        try {
            $response = Http::timeout($timeout)
                ->withToken($apiKey)
                ->post($baseUrl.'/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.2,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('ai.llm.request_failed', ['status' => $response->status()]);

                return null;
            }

            return (string) data_get($response->json(), 'choices.0.message.content');
        } catch (\Throwable $e) {
            Log::warning('ai.llm.exception', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractJson(string $content): ?array
    {
        $content = trim($content);
        if (str_starts_with($content, '{')) {
            $decoded = json_decode($content, true);

            return is_array($decoded) ? $decoded : null;
        }

        if (preg_match('/\{.*\}/s', $content, $m)) {
            $decoded = json_decode($m[0], true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }
}

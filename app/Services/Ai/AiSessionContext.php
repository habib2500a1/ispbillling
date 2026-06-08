<?php

namespace App\Services\Ai;

/**
 * Conversational session filters (UI state only — no DB).
 */
final class AiSessionContext
{
    /** @var array<string, mixed> */
    private array $filters = [];

    /** @var list<array{role: string, text: string}> */
    private array $history = [];

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->filters;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function mergeFilters(array $filters): void
    {
        $this->filters = array_merge($this->filters, array_filter($filters, fn ($v) => $v !== null && $v !== ''));
    }

    public function clearFilters(): void
    {
        $this->filters = [];
    }

    public function addMessage(string $role, string $text): void
    {
        $this->history[] = ['role' => $role, 'text' => $text];
        if (count($this->history) > 40) {
            $this->history = array_slice($this->history, -40);
        }
    }

    /**
     * @return list<array{role: string, text: string}>
     */
    public function history(): array
    {
        return $this->history;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'filters' => $this->filters,
            'history' => $this->history,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function hydrate(array $state): void
    {
        $this->filters = is_array($state['filters'] ?? null) ? $state['filters'] : [];
        $this->history = is_array($state['history'] ?? null) ? $state['history'] : [];
    }
}

<?php

namespace App\Services\Sales;

use App\Filament\Resources\SalesLeadResource;
use App\Models\SalesLead;
use Illuminate\Support\Collection;

final class SalesLeadKanbanService
{
    /**
     * @return array<string, array{label: string, color: string, leads: Collection<int, SalesLead>}>
     */
    public function board(?int $assigneeId = null): array
    {
        $query = SalesLead::query()
            ->with(['assignee:id,name', 'package:id,name'])
            ->when($assigneeId, fn ($q) => $q->where('assigned_to', $assigneeId))
            ->whereNull('converted_customer_id')
            ->orderBy('next_follow_up_at')
            ->orderByDesc('id');

        $grouped = $query->get()->groupBy(
            fn (SalesLead $lead): string => $this->normalizeStatus((string) $lead->status),
        );

        $columns = [];
        foreach ($this->columnStatuses() as $status => $meta) {
            $columns[$status] = [
                'label' => $meta['label'],
                'color' => $meta['color'],
                'leads' => $grouped->get($status, collect()),
            ];
        }

        return $columns;
    }

    public function move(int $leadId, string $status): SalesLead
    {
        $status = $this->normalizeStatus($status);
        $lead = SalesLead::query()->findOrFail($leadId);
        $lead->status = $status;
        $lead->save();

        return $lead->fresh(['assignee', 'package']);
    }

    public static function editUrl(SalesLead $lead): string
    {
        return SalesLeadResource::getUrl('edit', ['record' => $lead]);
    }

  /**
     * @return array<string, array{label: string, color: string}>
     */
    private function columnStatuses(): array
    {
        return [
            SalesLead::STATUS_NEW => ['label' => 'New', 'color' => 'border-sky-200 bg-sky-50/80'],
            SalesLead::STATUS_CONTACTED => ['label' => 'Contacted', 'color' => 'border-amber-200 bg-amber-50/80'],
            SalesLead::STATUS_QUALIFIED => ['label' => 'Qualified', 'color' => 'border-violet-200 bg-violet-50/80'],
            SalesLead::STATUS_WON => ['label' => 'Won', 'color' => 'border-emerald-200 bg-emerald-50/80'],
            SalesLead::STATUS_LOST => ['label' => 'Lost', 'color' => 'border-rose-200 bg-rose-50/80'],
        ];
    }

    private function normalizeStatus(string $status): string
    {
        return match ($status) {
            SalesLead::STATUS_CONTACTED,
            SalesLead::STATUS_QUALIFIED,
            SalesLead::STATUS_WON,
            SalesLead::STATUS_LOST => $status,
            default => SalesLead::STATUS_NEW,
        };
    }
}

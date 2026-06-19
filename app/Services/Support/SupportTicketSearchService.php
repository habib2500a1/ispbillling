<?php

namespace App\Services\Support;

use App\Models\SupportTicket;
use Illuminate\Database\Eloquent\Builder;

/**
 * Global ticket search — ticket ID, CID, mobile, ONU serial, MAC, OLT name.
 */
final class SupportTicketSearchService
{
    public function apply(Builder $query, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($term, $like): void {
            $q->where('ticket_number', 'ilike', $like)
                ->orWhere('subject', 'ilike', $like)
                ->orWhereHas('customer', function (Builder $cq) use ($term, $like): void {
                    $cq->where('customer_code', 'ilike', $like)
                        ->orWhere('name', 'ilike', $like)
                        ->orWhere('phone', 'ilike', $like)
                        ->orWhere('email', 'ilike', $like);
                })
                ->orWhereHas('customer.onuDevice', function (Builder $oq) use ($like): void {
                    $oq->where('serial_number', 'ilike', $like)
                        ->orWhere('mac_address', 'ilike', $like)
                        ->orWhere('display_name', 'ilike', $like);
                })
                ->orWhereHas('olt', function (Builder $oltq) use ($like): void {
                    $oltq->where('display_name', 'ilike', $like)
                        ->orWhere('hostname', 'ilike', $like)
                        ->orWhere('serial_number', 'ilike', $like);
                });

            if (ctype_digit($term)) {
                $q->orWhere('id', (int) $term)
                    ->orWhereHas('customer', fn (Builder $cq): Builder => $cq->where('id', (int) $term));
            }
        });
    }

    /**
     * @return list<array{type: string, label: string, url: string}>
     */
    public function quickResults(string $term, int $limit = 8): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        return SupportTicket::query()
            ->with(['customer:id,name,customer_code,phone'])
            ->tap(fn (Builder $q) => $this->apply($q, $term))
            ->whereNotIn('status', ['closed'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'ticket_number', 'subject', 'status', 'priority', 'customer_id'])
            ->map(fn (SupportTicket $t): array => [
                'type' => 'ticket',
                'label' => $t->ticket_number.' · '.($t->customer?->name ?? 'No customer').' — '.$t->subject,
                'url' => \App\Filament\Resources\SupportTicketResource::getUrl('edit', ['record' => $t]),
            ])
            ->all();
    }
}

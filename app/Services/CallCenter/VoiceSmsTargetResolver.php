<?php

namespace App\Services\CallCenter;

use App\Models\Customer;
use App\Models\VoiceSmsCampaign;
use App\Support\CustomerAccountScopes;
use App\Support\CustomerStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class VoiceSmsTargetResolver
{
    /**
     * @return Collection<int, Customer>
     */
    public function customers(VoiceSmsCampaign $campaign): Collection
    {
        $filters = is_array($campaign->target_filters) ? $campaign->target_filters : [];
        $preset = (string) ($filters['preset'] ?? 'all_active');
        $packageIds = array_values(array_filter(array_map('intval', (array) ($filters['package_ids'] ?? []))));

        $query = Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $campaign->tenant_id)
            ->where('status', '!=', CustomerStatus::TERMINATED)
            ->whereNotNull('phone')
            ->where('phone', '!=', '');

        $query = match ($preset) {
            'due_clients' => CustomerAccountScopes::applyWithBalanceDue($query, (int) $campaign->tenant_id),
            'expired' => CustomerAccountScopes::applyExpired($query),
            'free' => $query->where('subscriber_type', \App\Support\SubscriberType::FREE),
            default => $query->where('status', CustomerStatus::ACTIVE),
        };

        if ($packageIds !== []) {
            $query->whereIn('package_id', $packageIds);
        }

        return $query->orderBy('id')->get();
    }

    public function countTargets(VoiceSmsCampaign $campaign): int
    {
        return $this->customers($campaign)->count();
    }
}

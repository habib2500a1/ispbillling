<?php

namespace App\Services\Saas;

use App\Models\CustomerOnu;
use App\Models\CustomersInfo;
use App\Models\Olt;
use App\Models\RouterList;
use App\Models\SaasOperator;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class SaasQuotaService
{
    /**
     * @return array{used: int, max: int, remaining: int|null, unlimited: bool}
     */
    public function usage(SaasOperator $operator, string $resource): array
    {
        $max = (int) ($operator->{'max_'.$resource} ?? 0);
        $used = $this->count($operator, $resource);

        return [
            'used' => $used,
            'max' => $max,
            'unlimited' => $max <= 0,
            'remaining' => $max <= 0 ? null : max(0, $max - $used),
        ];
    }

    /**
     * @return array<string, array{used: int, max: int, remaining: int|null, unlimited: bool}>
     */
    public function snapshot(SaasOperator $operator): array
    {
        $out = [];
        foreach (['customers', 'olts', 'onus', 'routers', 'staff'] as $resource) {
            $out[$resource] = $this->usage($operator, $resource);
        }

        return $out;
    }

    public function assert(string $resource, ?SaasOperator $operator = null): void
    {
        $operator ??= SaasContext::operator();
        if (! $operator || SaasContext::isPlatformOwner()) {
            return;
        }

        $usage = $this->usage($operator, $resource);
        if ($usage['unlimited']) {
            return;
        }

        if ($usage['used'] >= $usage['max']) {
            throw new SaasQuotaException(
                __('Plan limit reached: :resource (:used / :max). Upgrade or ask the platform owner.', [
                    'resource' => $resource,
                    'used' => $usage['used'],
                    'max' => $usage['max'],
                ])
            );
        }
    }

    public function count(SaasOperator $operator, string $resource): int
    {
        $id = $operator->id;

        return match ($resource) {
            'customers' => $this->scopedCount(CustomersInfo::class, 'customers_infos', $id),
            'olts' => $this->scopedCount(Olt::class, 'olts', $id),
            'onus' => CustomerOnu::query()
                ->whereIn('customers_info_id', function ($q) use ($id) {
                    $q->select('id')->from('customers_infos')->where('saas_operator_id', $id);
                })
                ->count(),
            'routers' => $this->scopedCount(RouterList::class, 'router_lists', $id),
            'staff' => User::query()
                ->where('saas_operator_id', $id)
                ->where('id', '!=', $operator->user_id)
                ->count(),
            default => 0,
        };
    }

    /**
     * @param  class-string  $model
     */
    private function scopedCount(string $model, string $table, int $operatorId): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = $model::query()->withoutGlobalScope('saas_tenant');
        if (Schema::hasColumn($table, 'saas_operator_id')) {
            $query->where('saas_operator_id', $operatorId);
        }

        return $query->count();
    }
}

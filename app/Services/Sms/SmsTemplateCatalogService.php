<?php

namespace App\Services\Sms;

use App\Models\SaasOperator;
use App\Models\SmsTemplate;
use App\Support\SmsTemplateCatalog;
use Illuminate\Support\Facades\Schema;

final class SmsTemplateCatalogService
{
    public function syncMissing(?int $tenantId = null): int
    {
        return $this->syncForTenant($tenantId);
    }

    public function syncAllTenants(): int
    {
        $created = $this->syncForTenant(null);
        if (! Schema::hasTable('saas_operators')) {
            return $created;
        }

        foreach (SaasOperator::query()->pluck('id') as $id) {
            $created += $this->syncForTenant((int) $id);
        }

        return $created;
    }

    public function syncForTenant(?int $tenantId): int
    {
        if (! Schema::hasTable('sms_templates')) {
            return 0;
        }

        $hasTenant = Schema::hasColumn('sms_templates', 'saas_operator_id');
        if (! $hasTenant && $tenantId) {
            return 0;
        }

        $created = 0;

        foreach (SmsTemplateCatalog::defaults() as $row) {
            $query = SmsTemplate::query()->withoutGlobalScope('saas_tenant')->where('template_name', $row['key']);
            if ($hasTenant) {
                if ($tenantId) {
                    $query->where('saas_operator_id', $tenantId);
                } else {
                    $query->whereNull('saas_operator_id');
                }
            }

            if ($query->exists()) {
                continue;
            }

            $tpl = new SmsTemplate;
            if ($hasTenant) {
                $tpl->saas_operator_id = $tenantId;
            }
            $tpl->template_name = $row['key'];
            $tpl->display_name = $row['name'];
            $tpl->event_key = $row['event_key'];
            $tpl->template = $row['body'];
            $tpl->template_ex_en = $row['example_en'] ?? '';
            $tpl->template_ex_bn = $row['example_bn'] ?? '';
            $tpl->placeholders = $row['placeholders'];
            $tpl->sort_order = $row['sort_order'];
            $tpl->is_active = true;
            $tpl->save();
            $created++;
        }

        return $created;
    }

    /**
     * @return array{total: int, enabled: int, disabled: int}
     */
    public function stats(): array
    {
        if (! Schema::hasTable('sms_templates')) {
            return ['total' => 0, 'enabled' => 0, 'disabled' => 0];
        }

        $total = SmsTemplate::query()->count();
        $enabled = SmsTemplate::query()->where('is_active', true)->count();

        return [
            'total' => $total,
            'enabled' => $enabled,
            'disabled' => $total - $enabled,
        ];
    }
}

<?php

namespace App\Services\Tenant;

use App\Services\Sms\SmsTemplateService;
use Database\Seeders\AutomaticProcessSeeder;

final class TenantProvisioningService
{
    /**
     * Seed automation + SMS defaults when onboarding a new ISP tenant.
     *
     * @return array{automatic_processes: array{created: int, updated: int}, sms_templates: int}
     */
    public function provision(int $tenantId): array
    {
        app(TenantModuleSettingsService::class)->seedDefaults($tenantId);
        $processStats = app(AutomaticProcessSeeder::class)->syncForTenant($tenantId, fullRestore: false);
        $smsCount = app(SmsTemplateService::class)->syncMissingDefaults($tenantId);

        return [
            'automatic_processes' => $processStats,
            'sms_templates' => $smsCount,
        ];
    }
}

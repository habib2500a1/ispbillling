<?php

namespace App\Services\Optical;

use App\Models\Customer;
use App\Models\Device;

/**
 * @deprecated Use LegacyPortalOnuPipelineService directly.
 */
final class IspDigitalOnuPipelineService
{
    public function __construct(
        private readonly LegacyPortalOnuPipelineService $pipeline,
    ) {}

    public function tenantInventoryFresh(int $tenantId, ?int $maxAgeSeconds = null): bool
    {
        return $this->pipeline->tenantInventoryFresh($tenantId, $maxAgeSeconds);
    }

    public function syncAndLinkCustomer(Customer $customer, bool $forceOltSync = false): ?Device
    {
        return $this->pipeline->syncAndLinkCustomer($customer, $forceOltSync);
    }
}

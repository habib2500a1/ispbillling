<?php

namespace App\Services\Subscribers;

use App\Models\Customer;
use App\Models\Reseller;
use App\Models\ResellerCustomerTransfer;
use App\Models\User;
use App\Services\Resellers\ResellerQuotaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin-side subscriber move between resellers (Sheba-Fi "Move" parity).
 */
final class AdminSubscriberTransferService
{
    public function moveToReseller(Customer $customer, ?Reseller $toReseller, User $actor, ?string $reason = null): Customer
    {
        return DB::transaction(function () use ($customer, $toReseller, $actor, $reason): Customer {
            $fromResellerId = $customer->reseller_id;

            if ($toReseller !== null) {
                if ((int) $toReseller->tenant_id !== (int) $customer->tenant_id) {
                    throw ValidationException::withMessages(['reseller' => 'Cross-tenant transfer is not allowed.']);
                }

                app(ResellerQuotaService::class)->assertCanAddCustomer($toReseller);
                $customer->update(['reseller_id' => $toReseller->id]);
            } else {
                $customer->update(['reseller_id' => null]);
            }

            $meta = is_array($customer->meta) ? $customer->meta : [];
            $meta['last_reseller_move'] = [
                'from_reseller_id' => $fromResellerId,
                'to_reseller_id' => $toReseller?->id,
                'by_user_id' => $actor->id,
                'at' => now()->toIso8601String(),
                'reason' => $reason,
            ];
            $customer->forceFill(['meta' => $meta])->saveQuietly();

            static::recordTransferAudit($customer, $fromResellerId, $toReseller, $actor, $reason);

            return $customer->fresh() ?? $customer;
        });
    }

    private static function recordTransferAudit(
        Customer $customer,
        ?int $fromResellerId,
        ?Reseller $toReseller,
        User $actor,
        ?string $reason,
    ): void {
        if ($fromResellerId === null || $toReseller === null) {
            return;
        }

        if ((int) $fromResellerId === (int) $toReseller->id) {
            return;
        }

        ResellerCustomerTransfer::query()->create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'from_reseller_id' => $fromResellerId,
            'to_reseller_id' => $toReseller->id,
            'requested_by_reseller_id' => $fromResellerId,
            'approved_by' => $actor->id,
            'status' => ResellerCustomerTransfer::STATUS_COMPLETED,
            'reason' => $reason,
            'admin_notes' => 'Admin move (Filament)',
            'requested_at' => now(),
            'approved_at' => now(),
            'completed_at' => now(),
        ]);
    }
}

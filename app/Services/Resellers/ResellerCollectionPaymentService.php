<?php

namespace App\Services\Resellers;

use App\Models\Customer;
use App\Models\Reseller;
use App\Models\User;
use App\Services\Billing\StaffCollectionPaymentService;
use App\Support\ResellerPortalPermission;
use App\Support\ResellerPortalSession;
use Illuminate\Validation\ValidationException;

final class ResellerCollectionPaymentService
{
    public function collect(Reseller $reseller, Customer $customer, array $data): array
    {
        if (! app(ResellerPortalSession::class)->canPortal(ResellerPortalPermission::PAYMENT_COLLECT)) {
            throw ValidationException::withMessages(['permission' => 'Payment collection is not allowed.']);
        }

        app(ResellerCustomerService::class)->assertOwned($reseller, $customer);

        $user = $this->recorderUser($reseller);
        $mode = (string) ($data['allocation_mode'] ?? ResellerPaymentAllocationService::MODE_SINGLE);

        if ($mode === ResellerPaymentAllocationService::MODE_FIFO) {
            return app(ResellerPaymentAllocationService::class)->recordFifo(
                $user,
                $customer,
                $data,
                'reseller-portal',
            );
        }

        if ($mode === ResellerPaymentAllocationService::MODE_ADVANCE) {
            return app(ResellerPaymentAllocationService::class)->recordAdvanceWallet(
                $user,
                $customer,
                $data,
                'reseller-portal',
            );
        }

        return app(StaffCollectionPaymentService::class)->record(
            $user,
            $customer,
            $data,
            'reseller-portal',
        );
    }

    private function recorderUser(Reseller $reseller): User
    {
        if ($reseller->primary_user_id) {
            $user = User::query()->find($reseller->primary_user_id);
            if ($user !== null) {
                return $user;
            }
        }

        $user = User::query()
            ->where('tenant_id', $reseller->tenant_id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['super-admin', 'isp-admin', 'admin']))
            ->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'account' => 'No staff user linked for collections. Set Primary user on this reseller in admin.',
            ]);
        }

        return $user;
    }
}

<?php

namespace App\Services\Resellers;

use App\Models\Customer;
use App\Models\Reseller;
use App\Models\ResellerCustomerTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ResellerCustomerTransferService
{
    public function request(
        Customer $customer,
        Reseller $from,
        Reseller $to,
        Reseller $requestedBy,
        ?string $reason = null,
        bool $requireApproval = true,
    ): ResellerCustomerTransfer {
        if ((int) $customer->reseller_id !== (int) $from->id) {
            throw ValidationException::withMessages(['customer' => 'Customer is not owned by the source reseller.']);
        }

        if ($from->tenant_id !== $to->tenant_id) {
            throw ValidationException::withMessages(['reseller' => 'Cross-tenant transfer is not allowed.']);
        }

        $pending = ResellerCustomerTransfer::query()
            ->where('customer_id', $customer->id)
            ->where('status', ResellerCustomerTransfer::STATUS_PENDING)
            ->exists();

        if ($pending) {
            throw ValidationException::withMessages(['transfer' => 'A pending transfer already exists for this customer.']);
        }

        app(ResellerQuotaService::class)->assertCanAddCustomer($to);

        $transfer = ResellerCustomerTransfer::query()->create([
            'tenant_id' => $from->tenant_id,
            'customer_id' => $customer->id,
            'from_reseller_id' => $from->id,
            'to_reseller_id' => $to->id,
            'requested_by_reseller_id' => $requestedBy->id,
            'status' => $requireApproval ? ResellerCustomerTransfer::STATUS_PENDING : ResellerCustomerTransfer::STATUS_APPROVED,
            'reason' => $reason,
            'requested_at' => now(),
            'approved_at' => $requireApproval ? null : now(),
        ]);

        if (! $requireApproval) {
            $this->complete($transfer);
        }

        app(ResellerPortalActivityLogger::class)->log(
            $requestedBy,
            'customer.transfer.requested',
            $customer,
            ['to_reseller_id' => $to->id],
        );

        return $transfer;
    }

    public function approve(ResellerCustomerTransfer $transfer, User $approver, ?string $notes = null): ResellerCustomerTransfer
    {
        if ($transfer->status !== ResellerCustomerTransfer::STATUS_PENDING) {
            throw ValidationException::withMessages(['transfer' => 'Transfer is not pending approval.']);
        }

        $transfer->update([
            'status' => ResellerCustomerTransfer::STATUS_APPROVED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'admin_notes' => $notes,
        ]);

        return $this->complete($transfer->fresh());
    }

    public function reject(ResellerCustomerTransfer $transfer, User $approver, ?string $notes = null): ResellerCustomerTransfer
    {
        if ($transfer->status !== ResellerCustomerTransfer::STATUS_PENDING) {
            throw ValidationException::withMessages(['transfer' => 'Transfer is not pending.']);
        }

        $transfer->update([
            'status' => ResellerCustomerTransfer::STATUS_REJECTED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'admin_notes' => $notes,
        ]);

        return $transfer;
    }

    public function complete(ResellerCustomerTransfer $transfer): ResellerCustomerTransfer
    {
        return DB::transaction(function () use ($transfer): ResellerCustomerTransfer {
            $customer = Customer::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($transfer->customer_id);
            $customer->update(['reseller_id' => $transfer->to_reseller_id]);

            $transfer->update([
                'status' => ResellerCustomerTransfer::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            return $transfer->fresh();
        });
    }
}

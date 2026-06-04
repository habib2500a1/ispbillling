<?php

namespace App\Services\Inventory;

use App\Models\Customer;
use App\Models\Device;
use App\Models\StoreDeviceLoan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StoreDeviceLoanService
{
    public function issue(
        Device $device,
        Customer $customer,
        User $issuer,
        string $conditionOut = 'G',
        ?\DateTimeInterface $dueReturnAt = null,
        ?string $notes = null,
    ): StoreDeviceLoan {
        if ($device->customer_id !== null && (int) $device->customer_id !== (int) $customer->id) {
            throw ValidationException::withMessages([
                'device' => 'Device is already assigned to another subscriber.',
            ]);
        }

        $open = StoreDeviceLoan::query()
            ->where('device_id', $device->id)
            ->where('status', StoreDeviceLoan::STATUS_ISSUED)
            ->exists();

        if ($open) {
            throw ValidationException::withMessages([
                'device' => 'This device already has an open loan.',
            ]);
        }

        return DB::transaction(function () use ($device, $customer, $issuer, $conditionOut, $dueReturnAt, $notes): StoreDeviceLoan {
            $device->update([
                'customer_id' => $customer->id,
                'status' => 'assigned',
            ]);

            return StoreDeviceLoan::query()->create([
                'tenant_id' => $customer->tenant_id,
                'device_id' => $device->id,
                'customer_id' => $customer->id,
                'issued_by' => $issuer->id,
                'status' => StoreDeviceLoan::STATUS_ISSUED,
                'condition_out' => $conditionOut,
                'issued_at' => now(),
                'due_return_at' => $dueReturnAt,
                'issue_notes' => $notes,
            ]);
        });
    }

    public function returnDevice(
        StoreDeviceLoan $loan,
        User $returnedBy,
        string $conditionIn = 'G',
        ?string $notes = null,
    ): StoreDeviceLoan {
        if ($loan->status !== StoreDeviceLoan::STATUS_ISSUED) {
            throw ValidationException::withMessages([
                'loan' => 'Loan is not open for return.',
            ]);
        }

        return DB::transaction(function () use ($loan, $returnedBy, $conditionIn, $notes): StoreDeviceLoan {
            $loan->update([
                'status' => StoreDeviceLoan::STATUS_RETURNED,
                'condition_in' => $conditionIn,
                'returned_at' => now(),
                'returned_by' => $returnedBy->id,
                'return_notes' => $notes,
            ]);

            $device = $loan->device;
            if ($device !== null) {
                $device->update([
                    'customer_id' => null,
                    'status' => 'in_stock',
                ]);
            }

            return $loan->fresh() ?? $loan;
        });
    }
}

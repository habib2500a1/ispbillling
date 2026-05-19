<?php

namespace App\Services\Subscribers;

use App\Models\CollectorCollection;
use App\Models\Customer;
use App\Models\Device;
use App\Models\SalesLead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class CustomerDeletionService
{
    /**
     * Remove rows that block customer delete (FK restrict) and detach optional links.
     */
    public function prepareDelete(Customer $customer): void
    {
        $customerId = (int) $customer->id;

        CollectorCollection::withoutGlobalScopes()
            ->where('customer_id', $customerId)
            ->delete();

        Device::withoutGlobalScopes()
            ->where('customer_id', $customerId)
            ->update(['customer_id' => null]);

        SalesLead::withoutGlobalScopes()
            ->where('converted_customer_id', $customerId)
            ->update(['converted_customer_id' => null]);

        $customer->tokens()->delete();
    }

    /**
     * @throws \Throwable
     */
    public function delete(Customer $customer): void
    {
        DB::transaction(function () use ($customer): void {
            $this->prepareDelete($customer);
            $customer->delete();
        });
    }

    /**
     * @param  iterable<int, Customer>  $customers
     * @return array{deleted: int, failed: list<array{id: int, code: ?string, error: string}>}
     */
    public function deleteMany(iterable $customers): array
    {
        $deleted = 0;
        $failed = [];

        foreach ($customers as $customer) {
            if (! $customer instanceof Customer) {
                continue;
            }

            try {
                $this->delete($customer);
                $deleted++;
            } catch (\Throwable $e) {
                Log::warning('customer.delete_failed', [
                    'customer_id' => $customer->id,
                    'customer_code' => $customer->customer_code,
                    'error' => $e->getMessage(),
                ]);
                $failed[] = [
                    'id' => (int) $customer->id,
                    'code' => $customer->customer_code,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return ['deleted' => $deleted, 'failed' => $failed];
    }
}

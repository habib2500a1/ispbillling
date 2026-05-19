<?php

namespace App\Services\Sales;

use App\Models\Customer;
use App\Models\SalesLead;
use App\Support\CustomerStatus;
use App\Support\SubscriberType;
use Illuminate\Support\Facades\DB;

final class SalesLeadConversionService
{
    public function convert(SalesLead $lead): Customer
    {
        if ($lead->converted_customer_id !== null) {
            $existing = Customer::query()->find($lead->converted_customer_id);
            if ($existing !== null) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($lead): Customer {
            $customer = Customer::createTrusted([
                'tenant_id' => $lead->tenant_id,
                'name' => $lead->name,
                'phone' => $lead->phone,
                'email' => $lead->email,
                'address' => $lead->address,
                'area_id' => $lead->area_id,
                'zone_id' => $lead->zone_id,
                'package_id' => $lead->package_id,
                'status' => CustomerStatus::ACTIVE,
                'subscriber_type' => SubscriberType::STANDARD,
                'joined_at' => now()->toDateString(),
                'kyc_status' => 'pending',
                'radius_username' => $lead->phone ? preg_replace('/\D+/', '', $lead->phone) : null,
                'notes' => trim(($lead->notes ?? '')."\n\nConverted from sales lead #{$lead->id}"),
            ]);

            $lead->update([
                'status' => SalesLead::STATUS_WON,
                'converted_customer_id' => $customer->id,
            ]);

            return $customer;
        });
    }
}

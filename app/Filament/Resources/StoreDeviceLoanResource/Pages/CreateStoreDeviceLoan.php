<?php

namespace App\Filament\Resources\StoreDeviceLoanResource\Pages;

use App\Filament\Resources\StoreDeviceLoanResource;
use App\Models\Customer;
use App\Models\Device;
use App\Services\Inventory\StoreDeviceLoanService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStoreDeviceLoan extends CreateRecord
{
    protected static string $resource = StoreDeviceLoanResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $device = Device::query()->findOrFail($data['device_id']);
        $customer = Customer::query()->findOrFail($data['customer_id']);

        return app(StoreDeviceLoanService::class)->issue(
            $device,
            $customer,
            auth()->user(),
            (string) ($data['condition_out'] ?? 'G'),
            isset($data['due_return_at']) ? \Illuminate\Support\Carbon::parse($data['due_return_at']) : null,
            $data['issue_notes'] ?? null,
        );
    }
}

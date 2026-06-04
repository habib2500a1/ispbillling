<?php

namespace App\Filament\Resources\VendorPaymentResource\Pages;

use App\Filament\Resources\VendorPaymentResource;
use App\Models\VendorPayment;
use App\Services\Accounting\VendorPaymentService;
use Filament\Resources\Pages\CreateRecord;

class CreateVendorPayment extends CreateRecord
{
    protected static string $resource = VendorPaymentResource::class;

    public function mount(): void
    {
        parent::mount();

        if (request()->query('type') === VendorPayment::TYPE_GENERAL) {
            $this->form->fill([
                'expense_type' => VendorPayment::TYPE_GENERAL,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'vat_amount' => 0,
            ]);
        }
    }

    public function getTitle(): string
    {
        return 'Add expense';
    }

    public function getHeading(): string
    {
        return 'Add expense';
    }

    public function getSubheading(): ?string
    {
        return 'Vendor payment, or general expense without vendor (office, utility, transport, etc.)';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return self::normalizeExpensePayload($data);
    }

    protected function afterCreate(): void
    {
        app(VendorPaymentService::class)->recordPayment($this->record);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeExpensePayload(array $data): array
    {
        $type = $data['expense_type'] ?? VendorPayment::TYPE_VENDOR;

        if ($type === VendorPayment::TYPE_GENERAL) {
            $data['vendor_id'] = null;
        } else {
            $data['expense_category'] = null;
            $data['payee_name'] = null;
            $data['expense_type'] = VendorPayment::TYPE_VENDOR;
        }

        return $data;
    }
}

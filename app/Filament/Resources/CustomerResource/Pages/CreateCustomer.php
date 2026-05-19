<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Pages\Concerns\HasMobileSubscriberFormLayout;
use App\Support\OpticalCustomerSync;
use App\Services\Optical\CustomerOnuAutoProvisionService;
use App\Support\CustomerStatus;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    use HasMobileSubscriberFormLayout;

    protected static string $resource = CustomerResource::class;

    public function getSubheading(): ?string
    {
        return 'Start on the Essentials tab: name, phone, package, activation/expire dates, PPP username, and ONU if available.';
    }

    protected function getCreateFormAction(): Actions\Action
    {
        return parent::getCreateFormAction()
            ->label('Register subscriber');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['joined_at'] = $data['joined_at'] ?? now()->toDateString();
        $data['kyc_status'] = $data['kyc_status'] ?? 'pending';
        $data['status'] = $data['status'] ?? CustomerStatus::ACTIVE;
        $data['billing_mode'] = $data['billing_mode'] ?? 'postpaid';
        $data['billing_day'] = $data['billing_day'] ?? (int) now()->format('j');
        $data['grace_period_days'] = $data['grace_period_days'] ?? 10;
        $data['account_balance'] = $data['account_balance'] ?? 0;
        $data['network_access_state'] = $data['network_access_state'] ?? 'active';

        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        $data['meta'] = array_merge([
            'notify_sms' => true,
            'auto_invoice' => true,
            'auto_pppoe' => true,
            'auto_onu' => (bool) config('optical.auto_provision_customer_onu', true),
            'auto_activate' => true,
            'auto_suspend' => true,
            'installation_status' => 'pending',
        ], $meta);

        return $data;
    }

    protected function afterCreate(): void
    {
        $onuId = $this->form->getState()['onu_device_pick'] ?? null;

        if ($onuId) {
            $onu = app(CustomerOnuAutoProvisionService::class)
                ->assignOnuToCustomer($this->record, (int) $onuId);

            if ($onu !== null) {
                Notification::make()
                    ->title('ONU linked')
                    ->body("{$onu->display_name} · RX ".($onu->rx_power_dbm ?? '—').' dBm')
                    ->success()
                    ->send();
            }

            return;
        }

        if (config('optical.auto_sync_on_customer_save', true)) {
            OpticalCustomerSync::dispatch($this->record, true);
        }
    }

    protected function getRedirectUrl(): string
    {
        return CustomerResource::getUrl('view', ['record' => $this->record]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Subscriber registered')
            ->body('360° view opens next — billing, PPP, ONU signal, and live traffic appear there.')
            ->success();
    }
}

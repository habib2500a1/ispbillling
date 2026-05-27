<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Forms\SubscriberFormSchema;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Pages\Concerns\ActivatesSubscriberLine;
use App\Filament\Resources\CustomerResource\Pages\Concerns\HasMobileSubscriberFormLayout;
use App\Support\OpticalCustomerSync;
use Filament\Forms\Form;
use App\Services\Optical\CustomerOnuAutoProvisionService;
use App\Services\Billing\CustomerActivationBillingService;
use App\Services\Subscribers\CustomerLineActivationService;
use App\Support\BillingDefaults;
use App\Support\CustomerStatus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Arr;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    use ActivatesSubscriberLine;
    use HasMobileSubscriberFormLayout;

    protected static string $resource = CustomerResource::class;

    public function getSubheading(): ?string
    {
        return 'Step 1: customer details. Step 2: PPPoE username & password (one place). Then register.';
    }

    public function form(Form $form): Form
    {
        return SubscriberFormSchema::configureCreateWizard($form);
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
        $data['billing_mode'] = $data['billing_mode'] ?? 'prepaid';
        $data['billing_day'] = max(1, min(28, (int) ($data['billing_day'] ?? BillingDefaults::billingDay())));
        $data['grace_period_days'] = $data['grace_period_days'] ?? 10;
        $expireDay = (int) (Arr::get($this->form->getState(), 'expire_day') ?? BillingDefaults::defaultExpireDay());
        $data['service_expires_at'] = BillingDefaults::dateFromExpireDay($expireDay);
        $data['account_balance'] = $data['account_balance'] ?? 0;
        $data['network_access_state'] = $data['network_access_state'] ?? 'active';

        if (filled($data['mikrotik_secret_name'] ?? null) && blank($data['radius_username'] ?? null)) {
            $data['radius_username'] = trim((string) $data['mikrotik_secret_name']);
        }

        if (blank($data['portal_password'] ?? null)) {
            $data['portal_password'] = Hash::make(
                (string) config('portal.default_password', '123456'),
            );
        }

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
        if (config('billing.bill_on_customer_create', true)) {
            $cycle = (string) (Arr::get($this->form->getState(), 'first_bill_cycle')
                ?? CustomerActivationBillingService::defaultFirstBillCycle((string) $this->record->billing_mode));
            $bill = app(CustomerActivationBillingService::class)->issueFirstBillIfRequested($this->record->fresh(), $cycle);
            if ($bill['invoice'] !== null) {
                Notification::make()
                    ->title('First bill created')
                    ->body($bill['message'].($bill['settled'] ? ' Wallet applied.' : ''))
                    ->success()
                    ->send();
            }
        }

        $formState = $this->form->getState();
        $service = app(CustomerLineActivationService::class);

        if ($service->shouldActivateFromRegisterForm($formState)) {
            $this->runLineActivationAfterRegister($this->record->fresh(), $formState);

            return;
        }

        $onuId = $formState['onu_device_pick'] ?? null;

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

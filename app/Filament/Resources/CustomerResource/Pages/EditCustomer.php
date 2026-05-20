<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Pages\Concerns\HasMobileSubscriberFormLayout;
use App\Models\Customer;
use App\Support\BillingDefaults;
use Illuminate\Support\Arr;
use App\Services\Optical\CustomerOnuAutoProvisionService;
use App\Services\Subscribers\CustomerDeletionService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    use HasMobileSubscriberFormLayout;

    protected static string $resource = CustomerResource::class;

    /**
     * Never pre-fill password fields from DB (hashes/ciphertext). Avoids double-hashing portal
     * password on save and keeps MikroTik PPP field blank unless staff enters a new value.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['portal_password'] = null;
        $data['mikrotik_ppp_password'] = null;
        $data['onu_device_pick'] = $this->record->devices()->where('type', 'onu')->value('id');
        $data['expire_day'] = BillingDefaults::expireDayFromDate($this->record->service_expires_at?->toDateString());

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (blank($data['customer_code'] ?? null)) {
            unset($data['customer_code']);
        }

        if (filled($data['mikrotik_secret_name'] ?? null) && blank($data['radius_username'] ?? null)) {
            $data['radius_username'] = trim((string) $data['mikrotik_secret_name']);
        }

        if (isset($data['meta']) && is_array($data['meta'])) {
            $existing = is_array($this->record->meta) ? $this->record->meta : [];
            $data['meta'] = array_replace($existing, $data['meta']);
        }

        $expireDay = (int) (Arr::get($this->form->getState(), 'expire_day') ?? 0);
        if ($expireDay >= 1) {
            $data['service_expires_at'] = BillingDefaults::dateFromExpireDay($expireDay);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $onuId = $this->form->getState()['onu_device_pick'] ?? null;
        if (! $onuId) {
            return;
        }

        $onu = app(CustomerOnuAutoProvisionService::class)
            ->assignOnuToCustomer($this->record, (int) $onuId);

        if ($onu !== null) {
            Notification::make()
                ->title('ONU linked')
                ->body("{$onu->display_name} · RX ".($onu->rx_power_dbm ?? '—').' dBm')
                ->success()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view360')
                ->label('360° view')
                ->icon('heroicon-o-eye')
                ->url(fn (): string => CustomerResource::getUrl('view', ['record' => $this->record])),
            Actions\DeleteAction::make()
                ->using(fn (Customer $record) => app(CustomerDeletionService::class)->delete($record)),
        ];
    }
}

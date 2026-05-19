<?php

namespace App\Filament\Resources\CustomerResource\Widgets;

use App\Filament\Pages\ManageOpticalLaserSettings;
use App\Filament\Pages\OpticalMonitoringHub;
use App\Models\Customer;
use App\Services\Optical\SubscriberOpticalPowerPresenter;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class SubscriberOpticalPowerWidget extends Widget
{
    protected static bool $isLazy = true;

    protected static string $view = 'filament.resources.customer-resource.widgets.subscriber-optical-power';

    public ?Customer $record = null;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return true;
    }

    protected function getViewData(): array
    {
        /** @var Customer|null $customer */
        $customer = $this->record;
        if (! $customer instanceof Customer) {
            return ['snapshot' => ['linked' => false, 'rows' => [], 'hint' => null]];
        }

        return [
            'snapshot' => app(SubscriberOpticalPowerPresenter::class)->forCustomer($customer),
            'optical_noc_url' => OpticalMonitoringHub::getUrl(),
            'laser_settings_url' => ManageOpticalLaserSettings::canAccess()
                ? ManageOpticalLaserSettings::getUrl()
                : null,
        ];
    }

    public function getRecord(): ?Model
    {
        return $this->record;
    }
}

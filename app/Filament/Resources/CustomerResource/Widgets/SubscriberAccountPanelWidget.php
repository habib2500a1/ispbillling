<?php

namespace App\Filament\Resources\CustomerResource\Widgets;

use App\Filament\Pages\BillCollectionDesk;
use App\Filament\Pages\CollectionDeskReport;
use App\Filament\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\Payment;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class SubscriberAccountPanelWidget extends Widget
{
    protected static bool $isLazy = true;

    protected static string $view = 'filament.resources.customer-resource.widgets.subscriber-account-panel';

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
            return ['customer' => null];
        }

        $customer->loadMissing(['package:id,name,price_monthly']);

        $openBalance = (float) $customer->invoices()
            ->whereIn('status', ['open', 'partial', 'sent', 'overdue'])
            ->selectRaw('COALESCE(SUM(GREATEST(total - amount_paid, 0)), 0) as open_balance')
            ->value('open_balance');

        $recentPayments = Payment::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->orderByDesc('paid_at')
            ->limit(5)
            ->with('recorder:id,name')
            ->get();

        return [
            'customer' => $customer,
            'open_balance' => round($openBalance, 2),
            'recent_payments' => $recentPayments,
            'collect_url' => BillCollectionDesk::getUrl(['customer' => $customer->id]),
            'edit_url' => CustomerResource::getUrl('edit', ['record' => $customer->id]),
            'report_url' => CollectionDeskReport::getUrl().'?customer='.$customer->id,
        ];
    }

    public function getRecord(): ?Model
    {
        return $this->record;
    }
}

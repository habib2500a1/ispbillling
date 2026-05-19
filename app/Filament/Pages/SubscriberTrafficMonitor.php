<?php

namespace App\Filament\Pages;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Widgets\SubscriberLiveTrafficWidget;
use App\Models\Customer;
use App\Services\Bandwidth\BandwidthCollectionService;
use App\Support\TenantResolver;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class SubscriberTrafficMonitor extends Page
{
    public ?int $customerId = null;

    public ?Customer $customer = null;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static string $view = 'filament.pages.subscriber-traffic-monitor';

    protected static ?string $navigationLabel = 'Subscriber traffic';

    protected static ?string $title = 'Subscriber traffic monitor';

    protected static ?string $navigationGroup = 'Network';

    protected static ?int $navigationSort = 25;

    protected static ?string $slug = 'subscriber-traffic';

    public function mount(): void
    {
        $id = request()->integer('customer');
        if ($id <= 0) {
            return;
        }

        $this->customerId = $id;
        $this->customer = Customer::query()
            ->where('tenant_id', TenantResolver::requiredTenantId())
            ->find($id);
    }

    public function getTitle(): string|Htmlable
    {
        if ($this->customer === null) {
            return 'Subscriber traffic monitor';
        }

        $login = $this->customer->pppLoginName() ?: $this->customer->customer_code;

        return "Traffic monitor: {$login}";
    }

    public function refreshLiveData(): void
    {
        if (! config('bandwidth.subscriber_view_collect_on_poll', false)) {
            return;
        }

        if (! config('bandwidth.collection_enabled', true)) {
            return;
        }

        try {
            app(BandwidthCollectionService::class)->collectForTenant(TenantResolver::requiredTenantId());
        } catch (\Throwable) {
            //
        }
    }

    protected function getHeaderWidgets(): array
    {
        if ($this->customer === null) {
            return [];
        }

        return [
            SubscriberLiveTrafficWidget::make(['record' => $this->customer]),
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            Action::make('sync')
                ->label('Sync now')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => $this->customer !== null)
                ->action(function (): void {
                    try {
                        $result = app(BandwidthCollectionService::class)->collectForTenant(
                            TenantResolver::requiredTenantId(),
                        );
                        Notification::make()
                            ->title('Bandwidth sync complete')
                            ->body(sprintf(
                                'Matched %d · Online sessions %d',
                                $result['matched_subscribers'],
                                $result['sessions_open'],
                            ))
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Sync failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];

        if ($this->customer !== null) {
            $actions[] = Action::make('subscriber')
                ->label('Open subscriber')
                ->icon('heroicon-o-user')
                ->url(CustomerResource::getUrl('view', ['record' => $this->customer]));
        }

        return $actions;
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }
}

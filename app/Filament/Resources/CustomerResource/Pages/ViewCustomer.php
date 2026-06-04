<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Concerns\HandlesStrayChartPolls;
use App\Filament\Pages\ManageOpticalLaserSettings;
use App\Filament\Pages\SubscriberTrafficMonitor;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\InvoiceResource;
use App\Jobs\SyncCustomerNetworkAccessJob;
use App\Models\Customer;
use App\Models\Device;
use App\Models\PaymentLink;
use App\Services\BillPayment\PaymentLinkService;
use App\Support\OpticalCustomerSync;
use App\Services\Optical\CustomerOnuAutoProvisionService;
use App\Services\Optical\CustomerOnuMatcher;
use App\Services\Optical\LegacyPortalOnuPipelineService;
use App\Services\Optical\LegacyPortalOnuAutoLinkService;
use App\Services\Network\OltFdbMacBridgeService;
use App\Services\Optical\CustomerOnuSmartLinkService;
use App\Services\Import\LegacyPortalCustomerDetailsSyncService;
use App\Services\Optical\OnuSignalCollectionService;
use App\Services\Billing\CustomerLineGraceService;
use App\Services\Subscribers\CustomerLineActivationService;
use App\Services\Subscribers\CustomerServiceRenewalService;
use App\Services\Subscribers\SubscriberClientDetailsPresenter;
use Carbon\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ViewCustomer extends ViewRecord
{
    use HandlesStrayChartPolls;

    protected static string $resource = CustomerResource::class;

    protected static string $view = 'filament.resources.customer-resource.pages.view-customer';

    public function getTitle(): string
    {
        /** @var Customer $record */
        $record = $this->record;

        return $record->name;
    }

    public function getSubheading(): ?string
    {
        /** @var Customer $record */
        $record = $this->record;
        $code = $record->customer_code ?: '#'.$record->getKey();

        return $code.($record->phone ? ' · '.$record->phone : '');
    }

    public function extendThirtyDays(): void
    {
        $this->extendDaysLive(30);
    }

    public function extendFiveDays(): void
    {
        $this->extendDaysLive(5);
    }

    public function extendDaysLive(int $days): void
    {
        $days = max(1, min(730, $days));
        $this->extendServiceDays($days);
        $this->record->refresh();
    }

    private function extendServiceDays(int $days): void
    {
        /** @var Customer $record */
        $record = $this->record;
        $result = app(CustomerServiceRenewalService::class)->extendDays($record, $days);
        Notification::make()
            ->title('Service extended')
            ->body(sprintf('+%d days — new expiry: %s (MikroTik synced)', $days, $result['expires_at']))
            ->success()
            ->send();
    }

    /**
     * Shared calendar form: weekday + date in picker, helper, and summary.
     *
     * @param  callable(Customer): string  $defaultDate
     * @param  callable(Customer): \Carbon\Carbon  $minDate
     * @param  callable(Customer): \Carbon\Carbon  $maxDate
     * @return array<int, Forms\Components\Component>
     */
    protected function calendarUntilFormSchema(
        string $field,
        string $label,
        callable $defaultDate,
        callable $minDate,
        callable $maxDate,
        string $summaryHint,
    ): array {
        return [
            Forms\Components\DatePicker::make($field)
                ->label($label)
                ->required()
                ->native(false)
                ->suffixIcon('heroicon-o-calendar-days')
                ->closeOnDateSelection()
                ->minDate($minDate)
                ->maxDate($maxDate)
                ->default($defaultDate)
                ->displayFormat('l, d M Y')
                ->format('Y-m-d')
                ->live(onBlur: false)
                ->helperText(fn (Get $get) => filled($get($field))
                    ? 'Selected: '.CustomerLineGraceService::formatDisplayDate(Carbon::parse($get($field)))
                    : 'Pick a date — weekday and date show together.'),
            Forms\Components\Placeholder::make($field.'_summary')
                ->label('Summary')
                ->content(function (Get $get) use ($field, $summaryHint): HtmlString|string {
                    if (! filled($get($field))) {
                        return '—';
                    }

                    $date = Carbon::parse($get($field))->startOfDay();
                    $labelText = CustomerLineGraceService::formatDisplayDate($date);

                    return new HtmlString(
                        '<strong class="text-lg">'.$labelText.'</strong>'
                        .'<br><span class="text-sm text-gray-500">'.$summaryHint.'</span>'
                    );
                })
                ->visible(fn (Get $get) => filled($get($field))),
        ];
    }

    public function toggleNetworkAccess(): void
    {
        /** @var Customer $record */
        $record = $this->record;
        $suspend = ($record->network_access_state ?? 'active') !== 'suspended';

        if ($suspend) {
            $record->update(['network_access_state' => 'suspended']);
            Notification::make()->title('Network suspended')->warning()->send();
        } else {
            if (! app(\App\Services\Network\NetworkAccessCoordinator::class)->canAdminForceNetOn($record)) {
                Notification::make()
                    ->title('Cannot enable network')
                    ->body('Overdue balance — collect payment first.')
                    ->warning()
                    ->send();

                return;
            }
            \App\Support\CustomerNetworkSync::forceNetOn($record);
            Notification::make()->title('Network active')->success()->send();
        }

        if ($suspend) {
            SyncCustomerNetworkAccessJob::dispatchSync((int) $record->tenant_id, (int) $record->id);
        }
        $this->record->refresh();
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var Customer $customer */
        $customer = $this->record;
        $customer->load([
            'package:id,name,download_mbps,price_monthly',
            'area:id,name',
            'zone:id,name',
            'subzone:id,name',
            'reseller:id,name',
            'onuDevice' => fn ($query) => $query->select([
                'devices.id',
                'devices.customer_id',
                'devices.type',
                'devices.rx_power_dbm',
                'devices.tx_power_dbm',
                'devices.onu_oper_status',
                'devices.display_name',
                'devices.mac_address',
            ]),
            'activePppSession',
            'devices' => fn ($q) => $q->where('type', '!=', 'olt')->orderByDesc('id'),
            'contacts',
        ])->loadCount('documents');

        $this->queueOpticalBackgroundSync($customer);
    }

    /**
     * Never block the page on OLT SNMP / MikroTik — queue sync after response instead.
     */
    private function queueOpticalBackgroundSync(Customer $customer): void
    {
        if (! config('optical.legacy_portal_auto_sync', true)) {
            return;
        }

        if (! config('optical.auto_sync_on_customer_view', true)
            && ! config('optical.auto_provision_customer_onu', true)) {
            return;
        }

        $onu = $customer->primaryOnu();
        $needsPull = $onu === null || $onu->rx_power_dbm === null;

        if (! $needsPull) {
            return;
        }

        $force = false;
        if ($onu === null) {
            $force = ! app(LegacyPortalOnuPipelineService::class)
                ->tenantInventoryFresh((int) $customer->tenant_id);
        }

        OpticalCustomerSync::dispatch($customer, $force, afterResponse: true);
    }

    protected function getHeaderActions(): array
    {
        /** @var Customer $record */
        $record = $this->record;

        return [
            Actions\Action::make('collect_payment')
                ->label('Collect payment')
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->url(fn (Customer $record): string => \App\Filament\Pages\BillCollectionDesk::getUrl([
                    'customer' => $record->getKey(),
                ])),
            Actions\Action::make('live_call')
                ->label('লাইভ কল')
                ->icon('heroicon-o-phone')
                ->color('success')
                ->visible(fn (): bool => filled($this->record->phone)
                    && \App\Support\WebSipFeature::showsLiveCallUi(auth()->user()))
                ->alpineClickHandler(function (): string {
                    $tel = preg_replace('/\D+/', '', (string) $this->record->phone);

                    return 'window.ispWebSipLiveCall('.Js::from($tel).')';
                }),
            Actions\ActionGroup::make(
                \App\Filament\Support\ShebaSubscriberQuickActions::headerActions($record),
            )
                ->label('Quick actions')
                ->icon('heroicon-m-ellipsis-horizontal')
                ->color('gray')
                ->button(),
            Actions\Action::make('extend_days')
                ->label('Extend validity')
                ->icon('heroicon-o-calendar-days')
                ->color('success')
                ->modalWidth('md')
                ->modalHeading('Extend validity — pick a date')
                ->modalDescription('Choose the last valid day (weekday + date). MikroTik line turns ON (no invoice).')
                ->form($this->calendarUntilFormSchema(
                    field: 'valid_until',
                    label: 'Valid until (weekday + date)',
                    defaultDate: fn (Customer $record): string => CustomerServiceRenewalService::suggestedValidUntil($record)->toDateString(),
                    minDate: fn (Customer $record): \Carbon\Carbon => (
                        ($record->service_expires_at && $record->service_expires_at->isFuture()
                            ? $record->service_expires_at->copy()
                            : now()->copy())
                            ->addDay()
                            ->startOfDay()
                    ),
                    maxDate: fn (): \Carbon\Carbon => now()->addDays(730)->startOfDay(),
                    summaryHint: 'New validity end',
                ))
                ->action(function (array $data): void {
                    /** @var Customer $record */
                    $record = $this->record;
                    $result = app(CustomerServiceRenewalService::class)->extendUntil(
                        $record->fresh(),
                        Carbon::parse($data['valid_until']),
                    );
                    Notification::make()
                        ->title('Validity extended')
                        ->body(sprintf(
                            '+%d days — valid until: %s (MikroTik synced)',
                            $result['days'],
                            CustomerLineGraceService::formatDisplayDate(Carbon::parse($result['expires_at'])),
                        ))
                        ->success()
                        ->send();
                    $this->record->refresh();
                }),
            Actions\Action::make('extend_line_grace_month')
                ->label('Line grace')
                ->icon('heroicon-o-calendar-days')
                ->color('warning')
                ->modalWidth('md')
                ->visible(fn (Customer $record): bool => $record->package_id !== null)
                ->modalHeading('Line grace — this month')
                ->modalDescription('Keep the line ON until the selected date even if the bill is overdue (does not change permanent grace days).')
                ->form($this->calendarUntilFormSchema(
                    field: 'grace_until',
                    label: 'Line ON until (weekday + date)',
                    defaultDate: fn (Customer $record): string => CustomerLineGraceService::suggestedGraceUntil($record)->toDateString(),
                    minDate: fn (): \Carbon\Carbon => now()->startOfDay(),
                    maxDate: fn (): \Carbon\Carbon => now()->copy()->endOfMonth()->startOfDay(),
                    summaryHint: 'Line grace until',
                ))
                ->action(function (array $data): void {
                    /** @var Customer $record */
                    $record = $this->record;
                    $until = CustomerLineGraceService::extendUntil(
                        $record->fresh(),
                        Carbon::parse($data['grace_until']),
                    );
                    Notification::make()
                        ->title('Line grace set')
                        ->body('Line stays ON until '.CustomerLineGraceService::formatDisplayDate($until).'.')
                        ->success()
                        ->send();
                }),
            Actions\ActionGroup::make($this->subscriberToolsHeaderActions())
                ->label('More')
                ->icon('heroicon-o-ellipsis-horizontal')
                ->button()
                ->color('gray'),
            Actions\EditAction::make(),
        ];
    }

    /**
     * @return array<int, Actions\Action>
     */
    protected function subscriberToolsHeaderActions(): array
    {
        return [
            Actions\Action::make('assign_line')
                ->label('New line / charges')
                ->icon('heroicon-o-bolt')
                ->color('warning')
                ->modalHeading('New line — charges & device')
                ->modalDescription(fn (): string => 'Line charge is added to an invoice. Optional device is linked to the subscriber. Wallet can pay the balance.')
                ->form([
                    Forms\Components\Placeholder::make('wallet_hint')
                        ->label('Wallet balance')
                        ->content(function (): string {
                            /** @var Customer $record */
                            $record = $this->record;
                            $balance = (float) $record->account_balance;

                            return number_format($balance, 2).' BDT available';
                        }),
                    Forms\Components\TextInput::make('line_charge')
                        ->label('Line / connection charge (BDT)')
                        ->numeric()
                        ->minValue(0)
                        ->default(fn (): float => app(CustomerLineActivationService::class)->defaultLineCharge($this->record))
                        ->required(),
                    Forms\Components\Select::make('device_id')
                        ->label('Device (optional)')
                        ->searchable()
                        ->options(function (): array {
                            /** @var Customer $record */
                            $record = $this->record;

                            return Device::query()
                                ->where('tenant_id', $record->tenant_id)
                                ->where('type', '!=', 'olt')
                                ->where(function ($q) use ($record): void {
                                    $q->whereNull('customer_id')
                                        ->orWhere('customer_id', $record->id);
                                })
                                ->whereIn('status', ['in_stock', 'assigned'])
                                ->orderBy('display_name')
                                ->limit(400)
                                ->get()
                                ->mapWithKeys(fn (Device $d): array => [
                                    $d->id => trim(sprintf(
                                        '%s · %s · %s',
                                        $d->display_name ?: strtoupper((string) $d->type),
                                        $d->serial_number ?: $d->mac_address ?: '—',
                                        $d->status,
                                    )),
                                ])
                                ->all();
                        }),
                    Forms\Components\TextInput::make('device_charge')
                        ->label('Device sale / issue charge (BDT)')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->helperText('If a device is selected, catalog/lease price is used when left blank.'),
                    Forms\Components\Toggle::make('use_wallet')
                        ->label('Pay invoice from wallet')
                        ->default(true)
                        ->live()
                        ->helperText('Deduct due amount from subscriber wallet.'),
                    Forms\Components\TextInput::make('cash_amount')
                        ->label('Cash collected (BDT)')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->helperText('Cash taken now after wallet apply.'),
                    Forms\Components\Select::make('cash_method')
                        ->label('Payment method')
                        ->options([
                            'cash' => 'Cash',
                            'bkash' => 'bKash',
                            'nagad' => 'Nagad',
                            'bank' => 'Bank',
                            'other' => 'Other',
                        ])
                        ->default('cash')
                        ->native(false),
                    Forms\Components\Textarea::make('notes')
                        ->label('Notes')
                        ->rows(2)
                        ->maxLength(500),
                ])
                ->action(function (array $data): void {
                    /** @var Customer $record */
                    $record = $this->record;

                    try {
                        $result = app(CustomerLineActivationService::class)->activate($record, [
                            'line_charge' => (float) ($data['line_charge'] ?? 0),
                            'device_id' => $data['device_id'] ?? null,
                            'device_charge' => (float) ($data['device_charge'] ?? 0),
                            'use_wallet' => (bool) ($data['use_wallet'] ?? true),
                            'cash_amount' => (float) ($data['cash_amount'] ?? 0),
                            'cash_method' => (string) ($data['cash_method'] ?? 'cash'),
                            'notes' => $data['notes'] ?? null,
                        ]);

                        Notification::make()
                            ->title('Line activated')
                            ->body($result['message'])
                            ->success()
                            ->send();
                    } catch (\Illuminate\Validation\ValidationException $e) {
                        Notification::make()
                            ->title('Activation failed')
                            ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                            ->danger()
                            ->send();

                        throw $e;
                    }

                    $this->redirect(static::getUrl(['record' => $record]));
                }),
            Actions\Action::make('showPppPassword')
                ->label('Show PPPoE password')
                ->icon('heroicon-o-shield-check')
                ->color('gray')
                ->visible(fn (Customer $record): bool => filled($record->getAttributes()['mikrotik_ppp_password'] ?? null))
                ->requiresConfirmation()
                ->modalDescription('PPPoE secret password (decrypted). Only share with the subscriber securely.')
                ->action(function (): void {
                    /** @var Customer $record */
                    $record = $this->record;
                    $pwd = $record->mikrotik_ppp_password;
                    Notification::make()
                        ->title('PPPoE / RADIUS password')
                        ->body($pwd ? (string) $pwd : 'Not set')
                        ->success()
                        ->persistent()
                        ->send();
                }),
            Actions\Action::make('traffic_monitor')
                ->label('Live traffic graph')
                ->icon('heroicon-o-chart-bar-square')
                ->color('info')
                ->url(fn (): string => SubscriberTrafficMonitor::getUrl([
                    'customer' => $this->record->getKey(),
                ]))
                ->openUrlInNewTab(),
            Actions\ActionGroup::make($this->subscriberOnuToolActions())
                ->label('ONU / Optical')
                ->icon('heroicon-o-cpu-chip')
                ->color('gray'),
            Actions\Action::make('payment_link')
                ->label('Payment link')
                ->icon('heroicon-o-link')
                ->color('info')
                ->form([
                    Forms\Components\Select::make('purpose')
                        ->options([
                            PaymentLink::PURPOSE_INVOICE => 'Pay bill',
                            PaymentLink::PURPOSE_WALLET => 'Wallet top-up',
                        ])
                        ->default(PaymentLink::PURPOSE_INVOICE)
                        ->required(),
                    Forms\Components\TextInput::make('amount')
                        ->label('Fixed amount (optional)')
                        ->numeric()
                        ->minValue(10),
                    Forms\Components\Toggle::make('send_sms')
                        ->label('Send SMS')
                        ->default(true),
                ])
                ->action(function (array $data, PaymentLinkService $links): void {
                    /** @var Customer $record */
                    $record = $this->record;
                    $purpose = $data['purpose'] === PaymentLink::PURPOSE_WALLET
                        ? PaymentLink::PURPOSE_WALLET
                        : PaymentLink::PURPOSE_INVOICE;
                    $link = $links->create(
                        $record,
                        $purpose,
                        null,
                        isset($data['amount']) ? (float) $data['amount'] : null,
                        auth()->id(),
                    );
                    if (! empty($data['send_sms'])) {
                        $links->sendSms($link);
                    }
                    $wa = $links->whatsAppShareUrl($link);
                    Notification::make()
                        ->title('Payment link created')
                        ->body($link->publicUrl())
                        ->success()
                        ->persistent()
                        ->actions($wa ? [
                            \Filament\Notifications\Actions\Action::make('whatsapp')
                                ->label('WhatsApp')
                                ->url($wa)
                                ->openUrlInNewTab(),
                        ] : [])
                        ->send();
                }),
            Actions\Action::make('public_pay')
                ->label('Open /pay')
                ->icon('heroicon-o-credit-card')
                ->url(fn (): string => url('/pay?code='.urlencode((string) $this->record->customer_code)))
                ->openUrlInNewTab(),
            Actions\Action::make('invoices')
                ->label('Invoices')
                ->icon('heroicon-o-document-text')
                ->url(fn (): string => InvoiceResource::getUrl('index', [
                    'tableFilters' => [
                        'customer_id' => [
                            'value' => (string) $this->record->getKey(),
                        ],
                    ],
                ])),
            Actions\Action::make('net_on')
                ->label('Net ON')
                ->icon('heroicon-o-signal')
                ->color('success')
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var Customer $record */
                    $record = $this->record;
                    if (! app(\App\Services\Network\NetworkAccessCoordinator::class)->canAdminForceNetOn($record)) {
                        Notification::make()
                            ->title('Cannot enable network')
                            ->body('Overdue balance — collect first. Net ON when no due or validity extended.')
                            ->warning()
                            ->send();

                        return;
                    }
                    \App\Support\CustomerNetworkSync::forceNetOn($record);
                    Notification::make()
                        ->title('Network active')
                        ->body('MikroTik secret ON ('.$record->fresh()?->pppLoginName().'). Reboot ONU if there is no internet.')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('net_off')
                ->label('Net OFF')
                ->icon('heroicon-o-signal-slash')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var Customer $record */
                    $record = $this->record;
                    $record->update(['network_access_state' => 'suspended']);
                    SyncCustomerNetworkAccessJob::dispatchSync((int) $record->tenant_id, (int) $record->id);
                    Notification::make()->title('Network suspended')->success()->send();
                }),
        ];
    }

    /**
     * ONU / optical tools (collapsed under «ONU / Optical»).
     *
     * @return array<int, Actions\Action>
     */
    protected function subscriberOnuToolActions(): array
    {
        return [
            Actions\Action::make('smart_link_onu')
                ->label('Auto link ONU')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->visible(fn (Customer $record): bool => $record->primaryOnu() === null)
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var Customer $record */
                    $record = $this->record;
                    app(CustomerOnuSmartLinkService::class)->pruneInvalidLinks((int) $record->tenant_id);
                    $match = app(CustomerOnuSmartLinkService::class)->findConfidentMatch($record);
                    if ($match['onu'] !== null && $match['reason'] !== null) {
                        app(CustomerOnuAutoProvisionService::class)->assignOnuToCustomer(
                            $record,
                            (int) $match['onu']->id,
                            $match['reason'],
                            $match['score'],
                        );
                        Notification::make()->title('ONU linked')->success()->send();
                        $this->redirect(static::getUrl(['record' => $record]));

                        return;
                    }
                    Notification::make()->title('Auto link — no match')->warning()->send();
                }),
            Actions\Action::make('link_onu')
                ->label('Pick ONU')
                ->icon('heroicon-o-link')
                ->color('primary')
                ->visible(fn (Customer $record): bool => $record->primaryOnu() === null)
                ->form([
                    Forms\Components\Select::make('onu_device_id')
                        ->label('ONU')
                        ->searchable()
                        ->required()
                        ->options(function (): array {
                            /** @var Customer $record */
                            $record = $this->record;

                            return Device::query()
                                ->where('tenant_id', $record->tenant_id)
                                ->where('type', 'onu')
                                ->where(function ($q) use ($record): void {
                                    $q->whereNull('customer_id')
                                        ->orWhere('customer_id', $record->id);
                                })
                                ->orderByDesc('rx_power_dbm')
                                ->limit(300)
                                ->get()
                                ->mapWithKeys(fn (Device $onu): array => [
                                    $onu->id => trim(sprintf(
                                        '%s · %s',
                                        $onu->display_name ?: 'ONU',
                                        $onu->mac_address ?: $onu->serial_number ?: '—',
                                    )),
                                ])
                                ->all();
                        }),
                ])
                ->action(function (array $data): void {
                    /** @var Customer $record */
                    $record = $this->record;
                    $onu = Device::query()
                        ->where('tenant_id', $record->tenant_id)
                        ->where('type', 'onu')
                        ->find($data['onu_device_id']);
                    if ($onu === null) {
                        Notification::make()->title('ONU not found')->danger()->send();

                        return;
                    }
                    app(CustomerOnuAutoProvisionService::class)->assignOnuToCustomer($record, (int) $onu->id);
                    Notification::make()->title('ONU linked')->success()->send();
                    $this->redirect(static::getUrl(['record' => $record]));
                }),
            Actions\Action::make('refresh_onu_signal')
                ->label('ONU signal refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->visible(fn (Customer $record): bool => $record->primaryOnu() !== null)
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var Customer $record */
                    $record = $this->record;
                    app(OnuSignalCollectionService::class)->collectForTenant((int) $record->tenant_id);
                    Notification::make()->title('ONU signal refreshed')->success()->send();
                    $this->redirect(static::getUrl(['record' => $record]));
                }),
            Actions\Action::make('unlink_onu')
                ->label('Unlink ONU')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn (Customer $record): bool => $record->primaryOnu() !== null)
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var Customer $record */
                    $record = $this->record;
                    Device::query()
                        ->withoutGlobalScopes()
                        ->where('customer_id', $record->id)
                        ->where('type', 'onu')
                        ->update(['customer_id' => null]);
                    Notification::make()->title('ONU unlinked')->success()->send();
                    $this->redirect(static::getUrl(['record' => $record]));
                }),
        ];
    }

    public function hasInfolist(): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function getClientDetails(): array
    {
        /** @var Customer $customer */
        $customer = $this->record;

        return app(SubscriberClientDetailsPresenter::class)->forCustomer($customer);
    }
}

<?php

namespace App\Filament\Resources\CustomerResource\Pages;

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
use App\Services\Optical\IspDigitalOnuPipelineService;
use App\Services\Optical\CustomerOnuSmartLinkService;
use App\Services\Import\IspDigitalCustomerDetailsSyncService;
use App\Services\Optical\OnuSignalCollectionService;
use App\Services\Subscribers\CustomerLineActivationService;
use App\Services\Subscribers\CustomerServiceRenewalService;
use App\Services\Subscribers\SubscriberClientDetailsPresenter;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ViewCustomer extends ViewRecord
{
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
        /** @var Customer $record */
        $record = $this->record;
        $result = app(CustomerServiceRenewalService::class)->extendDays($record, 30);
        Notification::make()
            ->title('Service extended')
            ->body('New expiry: '.$result['expires_at'])
            ->success()
            ->send();
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
            $record->update(['status' => 'active', 'network_access_state' => 'active']);
            Notification::make()->title('Network active')->success()->send();
        }

        SyncCustomerNetworkAccessJob::dispatch((int) $record->tenant_id, (int) $record->id)->afterResponse();
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
        if (! config('optical.isp_digital_auto_sync', true)) {
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
            $force = ! app(IspDigitalOnuPipelineService::class)
                ->tenantInventoryFresh((int) $customer->tenant_id);
        }

        OpticalCustomerSync::dispatch($customer, $force, afterResponse: true);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('extend_30_days')
                ->label('Extend 30 days')
                ->icon('heroicon-o-calendar')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Quick recharge — extend 30 days')
                ->modalDescription('Extends service expiry by 30 days and syncs MikroTik/RADIUS access (no invoice).')
                ->action(function (): void {
                    $this->extendThirtyDays();
                    $this->redirect(static::getUrl(['record' => $this->record]));
                }),
            Actions\ActionGroup::make($this->subscriberToolsHeaderActions())
                ->label('More tools')
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
                ->label('নতুন লাইন / চার্জ')
                ->icon('heroicon-o-bolt')
                ->color('warning')
                ->modalHeading('নতুন লাইন — চার্জ ও ডিভাইস')
                ->modalDescription(fn (): string => 'লাইন চার্জ ইনভয়েসে যোগ হবে। ডিভাইস সিলেক্ট করলে সাবস্ক্রাইবারের কাছে লিংক হবে। ওয়ালেট থেকে বাকি টাকা কাটা যাবে।')
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
                        ->label('লাইন / সংযোগ চার্জ (BDT)')
                        ->numeric()
                        ->minValue(0)
                        ->default(fn (): float => app(CustomerLineActivationService::class)->defaultLineCharge($this->record))
                        ->required(),
                    Forms\Components\Select::make('device_id')
                        ->label('ডিভাইস (ঐচ্ছিক)')
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
                        ->label('ডিভাইস বিক্রয় / ইস্যু চার্জ (BDT)')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->helperText('ডিভাইস সিলেক্ট করলে খালি রাখলে ক্যাটালগ/লিজ মূল্য নেবে'),
                    Forms\Components\Toggle::make('use_wallet')
                        ->label('ওয়ালেট থেকে ইনভয়েস কাটুন')
                        ->default(true)
                        ->live()
                        ->helperText('সাবস্ক্রাইবারের wallet থেকে due amount কাটা হবে'),
                    Forms\Components\TextInput::make('cash_amount')
                        ->label('নগদ সংগ্রহ (BDT)')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->helperText('Wallet-এর পর যে টাকা বাকি, staff এখনই নগদ নিলে লিখুন।'),
                    Forms\Components\Select::make('cash_method')
                        ->label('পেমেন্ট মাধ্যম')
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
                        ->label('বিবরণ')
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
                            ->title('লাইন সক্রিয় হয়েছে')
                            ->body($result['message'])
                            ->success()
                            ->send();
                    } catch (\Illuminate\Validation\ValidationException $e) {
                        Notification::make()
                            ->title('সক্রিয় করা যায়নি')
                            ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                            ->danger()
                            ->send();

                        throw $e;
                    }

                    $this->redirect(static::getUrl(['record' => $record]));
                }),
            Actions\Action::make('extend_validity_no_charge')
                ->label('মেয়াদ বাড়ান (চার্জ ছাড়া)')
                ->icon('heroicon-o-calendar-days')
                ->color('success')
                ->form([
                    Forms\Components\TextInput::make('days')
                        ->label('দিন')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(730)
                        ->default(7)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    /** @var Customer $record */
                    $record = $this->record;
                    $days = (int) ($data['days'] ?? 7);
                    $base = $record->service_expires_at && $record->service_expires_at->isFuture()
                        ? $record->service_expires_at->copy()->startOfDay()
                        : now()->startOfDay();
                    $record->forceFill([
                        'service_expires_at' => $base->copy()->addDays($days)->toDateString(),
                        'status' => 'active',
                        'network_access_state' => 'active',
                    ])->save();
                    SyncCustomerNetworkAccessJob::dispatch((int) $record->tenant_id, (int) $record->id)->afterResponse();
                    Notification::make()
                        ->title('মেয়াদ আপডেট (ইনভয়েস ছাড়া)')
                        ->body('নতুন শেষ তারিখ: '.$record->fresh()?->service_expires_at?->toDateString())
                        ->success()
                        ->send();
                }),
            Actions\Action::make('extend_grace_no_charge')
                ->label('Grace বাড়ান (চার্জ ছাড়া)')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->form([
                    Forms\Components\TextInput::make('extra_days')
                        ->label('অতিরিক্ত দিন')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(60)
                        ->default(3)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    /** @var Customer $record */
                    $record = $this->record;
                    $extra = (int) ($data['extra_days'] ?? 0);
                    $newGrace = min(90, (int) $record->grace_period_days + $extra);
                    $record->update(['grace_period_days' => $newGrace]);
                    Notification::make()
                        ->title('Grace আপডেট')
                        ->body("নতুন grace: {$newGrace} দিন")
                        ->success()
                        ->send();
                }),
            Actions\Action::make('portalPassword')
                ->label('Portal login password')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->form([
                    Forms\Components\Placeholder::make('info')
                        ->content(fn (Customer $record): string => $record->portalAccessEnabled()
                            ? 'Portal login is enabled. Old passwords cannot be shown (encrypted). Set a new password below — copy it from the notification.'
                            : 'No portal password yet. Set one below to allow customer login.'),
                    Forms\Components\Toggle::make('generate')
                        ->label('Generate random password')
                        ->live()
                        ->default(false),
                    Forms\Components\Toggle::make('use_default')
                        ->label('Reset to default ('.config('portal.default_password', '123456').')')
                        ->live()
                        ->default(false)
                        ->visible(fn (Get $get): bool => ! (bool) $get('generate')),
                    Forms\Components\TextInput::make('new_password')
                        ->label('New portal password')
                        ->password()
                        ->revealable()
                        ->minLength(6)
                        ->required(fn (Get $get): bool => ! (bool) $get('generate') && ! (bool) $get('use_default'))
                        ->visible(fn (Get $get): bool => ! (bool) $get('generate') && ! (bool) $get('use_default')),
                ])
                ->action(function (array $data): void {
                    /** @var Customer $record */
                    $record = $this->record;
                    $plain = ! empty($data['generate'])
                        ? Str::password(10)
                        : (! empty($data['use_default'])
                            ? app(\App\Services\Portal\CustomerPortalAccessService::class)->defaultPassword()
                            : (string) ($data['new_password'] ?? ''));
                    if ($plain === '') {
                        Notification::make()->title('Password required')->danger()->send();

                        return;
                    }
                    $record->forceFill(['portal_password' => Hash::make($plain)])->save();
                    Notification::make()
                        ->title('Portal password updated')
                        ->body("Login: {$record->customer_code} (or phone/email). New password: {$plain}")
                        ->success()
                        ->persistent()
                        ->send();
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
            Actions\Action::make('mikrotik_olt_auto')
                ->label('MikroTik → OLT auto')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->visible(fn (Customer $record): bool => $record->primaryOnu() === null && $record->mikrotik_server_id !== null)
                ->requiresConfirmation()
                ->modalDescription('MikroTik PPP secret (comment, caller-id, last-caller-id) থেকে EPON/MAC নিয়ে OLT inventory খুঁজবে ও link করবে।')
                ->action(function (): void {
                    /** @var Customer $record */
                    $record = $this->record;
                    $onu = app(\App\Services\Optical\MikrotikOpticalBridgeService::class)
                        ->syncAndLinkFromMikrotik($record->fresh(), true);
                    if ($onu !== null) {
                        app(OnuSignalCollectionService::class)->collectForTenant((int) $record->tenant_id);
                        Notification::make()
                            ->title('ONU linked (MikroTik → OLT)')
                            ->body("{$onu->display_name} · RX ".($onu->rx_power_dbm ?? '—').' dBm')
                            ->success()
                            ->send();
                        $this->redirect(static::getUrl(['record' => $record]));

                        return;
                    }
                    Notification::make()
                        ->title('MikroTik → OLT auto link হয়নি')
                        ->body('MikroTik comment-এ EPON0/4:29 বা ONU MAC দিন। Router MAC (last-caller-id) OLT-এ থাকে না — rifat6.m-এর মতো।')
                        ->warning()
                        ->persistent()
                        ->send();
                }),
            Actions\Action::make('link_by_mac')
                ->label('MAC দিয়ে ONU খুঁজুন')
                ->icon('heroicon-o-finger-print')
                ->color('info')
                ->visible(fn (Customer $record): bool => $record->primaryOnu() === null)
                ->requiresConfirmation()
                ->modalDescription('PPP MAC, MAC binding, বা ONU MAC দিয়ে OLT inventory খুঁজবে। না পেলে SNMP sync করে আবার চেষ্টা।')
                ->action(function (): void {
                    /** @var Customer $record */
                    $record = $this->record;
                    $onu = CustomerOnuMatcher::linkCustomerByMacFromOlt($record->fresh(), true);
                    if ($onu !== null) {
                        app(OnuSignalCollectionService::class)->collectForTenant((int) $record->tenant_id);
                        Notification::make()
                            ->title('ONU linked (MAC)')
                            ->body("{$onu->display_name} · {$onu->mac_address} · RX ".($onu->rx_power_dbm ?? '—').' dBm')
                            ->success()
                            ->send();
                        $this->redirect(static::getUrl(['record' => $record]));

                        return;
                    }
                    Notification::make()
                        ->title('MAC দিয়ে ONU পাওয়া যায়নি')
                        ->body('এই MAC OLT inventory-তে নেই। ONU MAC সঠিক কিনা দেখুন (router MAC ≠ ONU MAC)। Edit → ONU MAC ফিল্ডে OLT MAC দিন।')
                        ->warning()
                        ->persistent()
                        ->send();
                }),
            Actions\Action::make('pull_isp_digital_network')
                ->label('ISP Digital → Network/ONU')
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('gray')
                ->visible(function (Customer $record): bool {
                    $meta = is_array($record->meta) ? $record->meta : [];
                    $raw = is_array($meta['isp_digital_raw'] ?? null) ? $meta['isp_digital_raw'] : [];

                    return filled($meta['legacy_id'] ?? null) || filled($raw['CustomerHeaderId'] ?? null);
                })
                ->requiresConfirmation()
                ->modalDescription('pay.anetbd.com Customer Details থেকে Device, MAC, Cable, ONU rent (যদি থাকে) — local meta-তে সেভ হবে। OLT optical আলাদা: «Sync OLT & link ONU»।')
                ->action(function (): void {
                    /** @var Customer $record */
                    $record = $this->record;
                    try {
                        $result = app(IspDigitalCustomerDetailsSyncService::class)->syncCustomer($record->fresh());
                        if (! empty($result['error'])) {
                            Notification::make()->title('ISP Digital')->body($result['error'])->warning()->send();

                            return;
                        }
                        if ($result['updated']) {
                            Notification::make()
                                ->title('ISP Digital network synced')
                                ->body('Updated: '.implode(', ', $result['fields']))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('ISP Digital — no new network fields')
                                ->body('Details page-এ Device/MAC/Rent খালি থাকলে Edit client-এ ONU rent হাতে দিন।')
                                ->warning()
                                ->send();
                        }
                        OpticalCustomerSync::dispatch($record->fresh(), false, afterResponse: true);
                        $this->redirect(static::getUrl(['record' => $record]));
                    } catch (\Throwable $e) {
                        Notification::make()->title('ISP Digital pull failed')->body($e->getMessage())->danger()->send();
                    }
                }),
            Actions\Action::make('sync_olt_and_link_onu')
                ->label('Sync OLT & link ONU')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (Customer $record): bool => $record->primaryOnu() === null)
                ->requiresConfirmation()
                ->modalDescription('OLT থেকে সব ONU আবার sync করবে, তারপর PPP login / EPON / MAC দিয়ে auto-link চেষ্টা করবে।')
                ->action(function (): void {
                    /** @var Customer $record */
                    $record = $this->record;
                    $olt = Device::query()
                        ->withoutGlobalScopes()
                        ->where('tenant_id', $record->tenant_id)
                        ->where('type', 'olt')
                        ->orderBy('id')
                        ->first();

                    if ($olt === null) {
                        Notification::make()->title('কোনো OLT নেই')->body('Network → OLT যোগ করুন।')->warning()->send();

                        return;
                    }

                    try {
                        $onu = app(IspDigitalOnuPipelineService::class)
                            ->syncAndLinkCustomer($record->fresh(), true);
                        if ($onu !== null) {
                            Notification::make()
                                ->title('ONU linked (ISP Digital sync)')
                                ->body(sprintf(
                                    '%s · RX %s · TX %s',
                                    $onu->display_name,
                                    $onu->rx_power_dbm !== null ? number_format((float) $onu->rx_power_dbm, 2).' dBm' : '—',
                                    $onu->tx_power_dbm !== null ? number_format((float) $onu->tx_power_dbm, 2).' dBm' : '—',
                                ))
                                ->success()
                                ->send();
                            $this->redirect(static::getUrl(['record' => $record]));

                            return;
                        }
                        Notification::make()
                            ->title('Sync হয়েছে, কিন্তু auto-link হয়নি')
                            ->body(sprintf(
                                'OLT inventory আপডেট হয়েছে। «%s» নামে ONU নেই — BDCOM OLT-এ ONU description = PPP login সেট করুন, তারপুৰ আবার sync চাপুন।',
                                $record->pppLoginName(),
                            ))
                            ->warning()
                            ->persistent()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('OLT sync failed')->body($e->getMessage())->danger()->send();
                    }
                }),
            Actions\Action::make('smart_link_onu')
                ->label('Auto link ONU (smart)')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('শুধু নিশ্চিত ম্যাচ হলে লিংক হবে: OLT-এ ONU নাম = PPP login, অথবা Notes-এ EPON0/3:45, অথবা ONU MAC মিল। ভুল লিংক আগে সরানো হবে।')
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
                        Notification::make()
                            ->title('Smart link OK')
                            ->body("{$match['onu']->display_name} · score {$match['score']}")
                            ->success()
                            ->send();
                        $this->redirect(static::getUrl(['record' => $record]));

                        return;
                    }
                    Notification::make()
                        ->title('Auto link — কোনো নিশ্চিত ম্যাচ নেই')
                        ->body('OLT-এ ONU description = PPP login সেট করুন, তারপর BDCOM sync চালান। অথবা নিচের «ONU সংযুক্ত করুন» দিয়ে EPON পোর্ট বেছে নিন।')
                        ->warning()
                        ->persistent()
                        ->send();
                }),
            Actions\Action::make('link_onu')
                ->label('ONU সংযুক্ত করুন')
                ->icon('heroicon-o-link')
                ->color('primary')
                ->modalHeading('OLT থেকে ONU বেছে নিন')
                ->modalDescription('ইনস্টল শীট বা OLT-এ যে EPON পোর্ট (যেমন EPON0/3:45) — সেটি খুঁজে সিলেক্ট করুন। একবার সিলেক্ট করলেই RX/TX দেখাবে।')
                ->form([
                    Forms\Components\Select::make('onu_device_id')
                        ->label('ONU (BDCOM inventory)')
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
                                        '%s · %s · RX %s · TX %s',
                                        $onu->display_name ?: 'ONU',
                                        $onu->mac_address ?: $onu->serial_number,
                                        $onu->rx_power_dbm !== null ? number_format((float) $onu->rx_power_dbm, 2).' dBm' : '—',
                                        $onu->tx_power_dbm !== null ? number_format((float) $onu->tx_power_dbm, 2).' dBm' : '—',
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

                    Device::query()
                        ->where('customer_id', $record->id)
                        ->where('type', 'onu')
                        ->where('id', '!=', $onu->id)
                        ->update(['customer_id' => null]);

                    $onu = app(CustomerOnuAutoProvisionService::class)
                        ->assignOnuToCustomer($record, (int) $onu->id);

                    Notification::make()
                        ->title('ONU সংযুক্ত হয়েছে')
                        ->body($onu ? sprintf(
                            '%s · RX %s · TX %s',
                            $onu->display_name,
                            $onu->rx_power_dbm !== null ? number_format((float) $onu->rx_power_dbm, 2).' dBm' : '—',
                            $onu->tx_power_dbm !== null ? number_format((float) $onu->tx_power_dbm, 2).' dBm' : '—',
                        ) : 'Saved')
                        ->success()
                        ->send();

                    $this->redirect(static::getUrl(['record' => $record]));
                }),
            Actions\Action::make('laser_thresholds')
                ->label('Laser thresholds')
                ->icon('heroicon-o-adjustments-vertical')
                ->color('gray')
                ->url(fn (): string => ManageOpticalLaserSettings::getUrl())
                ->visible(fn (): bool => ManageOpticalLaserSettings::canAccess()),
            Actions\Action::make('refresh_onu_signal')
                ->label('Refresh ONU signal')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->visible(fn (Customer $record): bool => $record->primaryOnu() !== null)
                ->requiresConfirmation()
                ->modalDescription('OLT থেকে এই subscriber-এর ONU RX/TX dBm signal এখনই sync করবে।')
                ->action(function (): void {
                    /** @var Customer $record */
                    $record = $this->record;
                    $onu = $record->primaryOnu();
                    if ($onu === null) {
                        Notification::make()->title('No ONU linked')->warning()->send();

                        return;
                    }
                    try {
                        $result = app(OnuSignalCollectionService::class)->collectForTenant((int) $record->tenant_id);
                        $onu->refresh();
                        $this->record->unsetRelation('onuDevice');
                        Notification::make()
                            ->title('ONU signal refreshed')
                            ->body(sprintf(
                                '%s · RX %s · TX %s',
                                $onu->display_name,
                                $onu->rx_power_dbm !== null ? number_format((float) $onu->rx_power_dbm, 2).' dBm' : '—',
                                $onu->tx_power_dbm !== null ? number_format((float) $onu->tx_power_dbm, 2).' dBm' : '—',
                            ))
                            ->success()
                            ->send();
                        $this->redirect(static::getUrl(['record' => $record]));
                    } catch (\Throwable $e) {
                        Notification::make()->title('Sync error')->body($e->getMessage())->danger()->send();
                    }
                }),
            Actions\Action::make('unlink_onu')
                ->label('ONU আনলিংক')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn (Customer $record): bool => $record->primaryOnu() !== null)
                ->requiresConfirmation()
                ->modalDescription('এই subscriber থেকে ONU link সরিয়ে দেবে। ONU inventory-তে ফিরে যাবে।')
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
                    $record->update([
                        'status' => 'active',
                        'network_access_state' => 'active',
                    ]);
                    SyncCustomerNetworkAccessJob::dispatch((int) $record->tenant_id, (int) $record->id)->afterResponse();
                    Notification::make()->title('Network active')->success()->send();
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
                    SyncCustomerNetworkAccessJob::dispatch((int) $record->tenant_id, (int) $record->id)->afterResponse();
                    Notification::make()->title('Network suspended')->success()->send();
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

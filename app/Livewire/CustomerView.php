<?php

namespace App\Livewire;

use App\Http\Controllers\MikrotikController;
use App\Http\Controllers\SMSController;
use App\Models\BillingInfo;
use App\Models\CustomerSmsLog;
use App\Models\CollectionSummary;
use App\Models\CustomersInfo;
use App\Models\PaymentSummary;
use App\Models\PPPSecrets;
use App\Models\SupportTicket;
use App\Services\Olt\CustomerOpticalPresenter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CustomerView extends Component
{
    public string $customerId = '';

    public string $encryptedId = '';

    public float $rxSpeed = 0;

    public float $txSpeed = 0;

    public string $trafficInterface = '';

    public string $trafficRouter = '';

    public float $lastPollTime = 0;

    public float $lastResolveTime = 0;

    public bool $showSmsModal = false;

    public string $smsMessage = '';

    public string $onuMac = '';

    public string $onuRx = '';

    public string $onuTx = '';

    public string $onuPon = '';

    public string $onuOlt = '';

    public ?string $portalTokenUrl = null;

    public string $expireDate = '';

    public function mount(string $id): void
    {
        if (! hasAccess(['Super Admin'], ['all-customer', 'edit-customer'])) {
            abort(403, 'Unauthorized action.');
        }

        $this->encryptedId = $id;
        $this->customerId = decrypt($id);
        $preview = session('portal_token_preview_'.$this->customerId);
        if (is_string($preview) && $preview !== '') {
            $this->portalTokenUrl = $preview;
            session()->forget('portal_token_preview_'.$this->customerId);
        }
        $this->bootstrapPortalToken();
        $this->bootstrapTraffic();
        $this->loadExpireDate();
    }

    protected function loadExpireDate(): void
    {
        $bill = BillingInfo::query()
            ->where('customer_bill_unique_id', $this->customerId)
            ->first();
        if ($bill?->extra_date && Carbon::parse($bill->extra_date)->gte(today())) {
            $this->expireDate = Carbon::parse($bill->extra_date)->format('Y-m-d');

            return;
        }
        $this->expireDate = now()->format('Y-m-d');
    }

    protected function bootstrapPortalToken(): void
    {
        if ($this->portalTokenUrl) {
            return;
        }

        $customer = CustomersInfo::with('pppUser')
            ->where('customer_unique_id', $this->customerId)
            ->first();

        if (! $customer?->pppUser) {
            return;
        }

        try {
            $plain = app(\App\Services\Portal\CustomerPortalAccessService::class)
                ->ensureAccessToken($customer);
            if ($plain !== '') {
                $this->portalTokenUrl = route('portal.access.token', ['token' => $plain]);
            }
        } catch (\Throwable) {
            // Portal token is optional; page should still load.
        }
    }

    public function generatePortalToken(): void
    {
        try {
            $plain = app(\App\Services\Portal\CustomerPortalAccessService::class)
                ->regenerateAccessToken($this->customer());
            $this->portalTokenUrl = route('portal.access.token', ['token' => $plain]);
            flash()->success(__('Portal token link ready — copy and share (no password needed).'));
        } catch (\Throwable $e) {
            flash()->error($e->getMessage());
        }
    }

    public function loginToPortal()
    {
        $customer = $this->customer();
        $ppp = $customer->pppUser;

        if (! $ppp) {
            flash()->error(__('Customer has no PPP user — portal login is not available.'));

            return;
        }

        Auth::shouldUse('ppp');
        Auth::guard('ppp')->login($ppp->fresh(), false);
        session()->regenerate();
        session([
            'portal_login_time' => now(),
            'portal_impersonated_by_admin' => Auth::guard('web')->id(),
        ]);

        return redirect()->route('filament.portal.pages.portal-dashboard');
    }

    protected function customer(): CustomersInfo
    {
        $customer = CustomersInfo::with([
            'pppUser',
            'billing',
            'official',
            'package',
            'customerAddress',
            'onus',
            'reseller',
        ])->where('customer_unique_id', $this->customerId)->first();

        if (! $customer) {
            abort(404, 'Customer not found.');
        }

        return $customer;
    }

    protected function bootstrapTraffic(): void
    {
        $customer = $this->customer();
        $ppp = $customer->pppUser;
        if (! $ppp?->router_name || ! $ppp?->username) {
            return;
        }

        $this->trafficRouter = $ppp->router_name;
        $resolved = $this->resolveInterfaceName($ppp->router_name, $ppp->username);
        $this->trafficInterface = $resolved ?: '<pppoe-'.$ppp->username.'>';
        $this->lastResolveTime = microtime(true);
    }

    public function isOnline(CustomersInfo $customer): bool
    {
        return ! empty($customer->pppUser?->uptime);
    }

    public function refreshPresence(): void
    {
        $ppp = $this->customer()->pppUser;
        if (! $ppp?->router_name || ! $ppp->username) {
            return;
        }

        try {
            app(MikrotikSync::class)->refreshOneSession($ppp->router_name, (string) $ppp->username);
        } catch (\Throwable) {
        }
    }

    protected function resolveInterfaceName(string $routerName, string $username): ?string
    {
        if (! $routerName || ! $username) {
            return null;
        }

        try {
            $ctrl = app(MikrotikController::class);
            $interfaces = $ctrl->getInterfaces($routerName);
            $lowerUser = strtolower($username);

            foreach ($interfaces as $iface) {
                $name = $iface['name'] ?? '';
                $lowerName = strtolower($name);

                if ($lowerName === "<pppoe-{$lowerUser}>"
                    || $lowerName === "pppoe-{$lowerUser}"
                    || $lowerName === "<l2tp-{$lowerUser}>"
                    || $lowerName === "<sstp-{$lowerUser}>"
                    || $lowerName === "<pptp-{$lowerUser}>"
                    || $lowerName === "<ovpn-{$lowerUser}>"
                    || $lowerName === $lowerUser
                ) {
                    return $name;
                }
            }

            foreach ($interfaces as $iface) {
                $name = $iface['name'] ?? '';
                if (str_contains(strtolower($name), $lowerUser)) {
                    return $name;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }

    public function pollTraffic(): void
    {
        if (! $this->trafficRouter || ! $this->trafficInterface) {
            return;
        }

        if (microtime(true) - $this->lastPollTime < 1.5) {
            return;
        }
        $this->lastPollTime = microtime(true);

        $customer = $this->customer();
        if (! $this->isOnline($customer)) {
            $this->rxSpeed = 0;
            $this->txSpeed = 0;

            return;
        }

        try {
            $ppp = $customer->pppUser;
            if ($this->rxSpeed == 0 && $this->txSpeed == 0 && (microtime(true) - $this->lastResolveTime > 15) && $ppp) {
                $this->lastResolveTime = microtime(true);
                $resolved = $this->resolveInterfaceName($ppp->router_name, $ppp->username);
                if ($resolved && $resolved !== $this->trafficInterface) {
                    $this->trafficInterface = $resolved;
                }
            }

            $data = app(MikrotikController::class)->getLiveTraffic($this->trafficRouter, $this->trafficInterface);
            if (isset($data['rx-bits-per-second']) || isset($data['tx-bits-per-second'])) {
                $this->rxSpeed = (float) ($data['rx-bits-per-second'] ?? 0);
                $this->txSpeed = (float) ($data['tx-bits-per-second'] ?? 0);
                $this->dispatch('customer-traffic-updated', rx: $this->rxSpeed, tx: $this->txSpeed);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function syncOnu(): void
    {
        $customer = $this->customer();
        $result = app(\App\Services\Olt\LocalOltOnuSyncService::class)->syncForCustomer($customer);

        if ($result['ok']) {
            flash()->success($result['message']);
        } else {
            flash()->warning($result['message']);
        }

        $this->loadOnuForm($customer->fresh(['onus']));
    }

    public function saveOnuManual(): void
    {
        $customer = $this->customer();
        app(CustomerOpticalPresenter::class)->saveManual($customer, [
            'mac_address' => $this->onuMac ?: null,
            'pon_port' => $this->onuPon ?: null,
            'olt_name' => $this->onuOlt ?: null,
            'rx_power_dbm' => $this->onuRx !== '' ? $this->onuRx : null,
            'tx_power_dbm' => $this->onuTx !== '' ? $this->onuTx : null,
            'oper_status' => 'manual',
        ]);

        flash()->success(__('ONU optical data saved.'));
    }

    protected function loadOnuForm(CustomersInfo $customer): void
    {
        $onu = $customer->primaryOnu();
        if (! $onu) {
            if ($this->onuOlt === '') {
                $this->onuOlt = (string) (\App\Models\Olt::query()->where('status', 'active')->value('name') ?? '');
            }

            return;
        }

        $this->onuMac = (string) ($onu->mac_address ?? '');
        $this->onuPon = (string) ($onu->pon_port ?? '');
        $this->onuOlt = (string) ($onu->olt_name ?? '');
        $this->onuRx = $onu->rx_power_dbm !== null ? (string) $onu->rx_power_dbm : '';
        $this->onuTx = $onu->tx_power_dbm !== null ? (string) $onu->tx_power_dbm : '';
    }

    public function openSmsModal(): void
    {
        $this->showSmsModal = true;
        if ($this->smsMessage === '') {
            $customer = $this->customer();
            $this->smsMessage = __('Dear :name, this is :company.', [
                'name' => $customer->customer_name,
                'company' => siteUrlSettings('site_name') ?: config('app.name'),
            ]);
        }
    }

    public function sendSms(): void
    {
        $customer = $this->customer();
        if (! filled($customer->mobile)) {
            flash()->error(__('Customer has no mobile number.'));

            return;
        }

        if (trim($this->smsMessage) === '') {
            flash()->error(__('Message cannot be empty.'));

            return;
        }

        $response = app(SMSController::class)->sendCustomSms([
            'recipient' => $customer->mobile,
            'customer_id' => $customer->customer_unique_id,
            'message' => $this->smsMessage,
            'source' => 'profile',
        ]);

        if (($response->success ?? false) || (method_exists($response, 'isSuccessful') && $response->isSuccessful())) {
            flash()->success(__('SMS sent successfully.'));
            $this->showSmsModal = false;
        } else {
            flash()->error($response->getMessage() ?? $response->message ?? __('SMS send failed.'));
        }
    }

    public function resendSms(int $id): void
    {
        $log = CustomerSmsLog::query()
            ->where('id', $id)
            ->where('customer_unique_id', $this->customerId)
            ->first();
        if (! $log) {
            flash()->error(__('SMS log not found.'));

            return;
        }

        $customer = $this->customer();
        $mobile = $customer->mobile ?: $log->mobile;
        if (! filled($mobile)) {
            flash()->error(__('Customer has no mobile number.'));

            return;
        }

        $response = app(SMSController::class)->sendCustomSms([
            'recipient' => $mobile,
            'customer_id' => $customer->customer_unique_id,
            'message' => $log->body,
            'source' => 'resend',
        ]);

        if (($response->success ?? false) || (method_exists($response, 'isSuccessful') && $response->isSuccessful())) {
            flash()->success(__('SMS resent successfully.'));
        } else {
            flash()->error($response->getMessage() ?? $response->message ?? __('SMS resend failed.'));
        }
    }

    public function addWallet(float $amount): void
    {
        $amount = max(0, $amount);
        if ($amount <= 0) {
            flash()->error(__('Enter a valid amount.'));

            return;
        }

        $bill = BillingInfo::where('customer_bill_unique_id', $this->customerId)->first();
        if (! $bill) {
            flash()->error(__('Billing not found.'));

            return;
        }

        $bill->advance = (float) $bill->advance + $amount;
        $bill->due_amount = max(0, (float) $bill->due_amount - $amount);
        $bill->save();

        flash()->success(__('Wallet credited :amount BDT.', ['amount' => number_format($amount, 2)]));
    }

    public function enableLine(): void
    {
        if (! hasAccess(['Super Admin'], ['enable-pending-customer', 'edit-customer'])) {
            flash()->error('Unauthorized.');

            return;
        }

        $customer = $this->customer();
        $customer->status = 'active';
        $customer->save();

        if ($customer->pppUser) {
            try {
                app(MikrotikController::class)->enablePPPSecret(
                    $customer->customer_unique_id,
                    $customer->pppUser->router_name,
                    $customer->pppUser->username
                );
                PPPSecrets::where('id', $customer->ppp_user_id)->update(['status' => 'active']);
            } catch (\Throwable $e) {
                flash()->error('Line enable failed: '.$e->getMessage());

                return;
            }
        }

        flash()->success('Line ON — customer active.');
    }

    public function disableLine(): void
    {
        if (! hasAccess(['Super Admin'], ['edit-customer'])) {
            flash()->error('Unauthorized.');

            return;
        }

        $customer = $this->customer();
        if ($customer->isVip()) {
            flash()->warning(__('VIP customers are exempt from auto line-off. Disable manually only if intended.'));

            return;
        }

        $customer->status = 'disable';
        $customer->disable_count = (int) $customer->disable_count + 1;
        $customer->save();

        if ($customer->pppUser) {
            try {
                app(MikrotikController::class)->disablePPPSecret(
                    $customer->customer_unique_id,
                    $customer->pppUser->router_name,
                    $customer->pppUser->username,
                    false
                );
                PPPSecrets::where('id', $customer->ppp_user_id)->update(['status' => 'disable']);
            } catch (\Throwable $e) {
                flash()->error('Line disable failed: '.$e->getMessage());

                return;
            }
        }

        flash()->success('Line OFF — customer disabled.');
    }

    public function kickPpp(): void
    {
        $customer = $this->customer();
        if (! $customer->pppUser?->username || ! $customer->pppUser?->router_name) {
            flash()->warning('No PPP user linked.');

            return;
        }

        try {
            $quoted = '"'.str_replace('"', '\\"', $customer->pppUser->username).'"';
            app(MikrotikController::class)->singleWrite(
                $customer->pppUser->router_name,
                '/ppp active remove [find name='.$quoted.']'
            );
            flash()->success('PPP session kicked.');
        } catch (\Throwable $e) {
            flash()->error('Kick failed: '.$e->getMessage());
        }
    }

    public function extendExpire(int $days = 30): void
    {
        $days = max(1, min(365, $days));
        $bill = BillingInfo::where('customer_bill_unique_id', $this->customerId)->first();
        if (! $bill) {
            flash()->error('Billing not found.');

            return;
        }

        $today = now()->startOfDay();
        $permanent = $bill->auto_disable_date
            ? Carbon::parse($bill->auto_disable_date)->startOfDay()
            : $today;
        $currentTemp = $bill->extra_date
            ? Carbon::parse($bill->extra_date)->startOfDay()
            : null;

        if ($currentTemp && $currentTemp->gte($today)) {
            $base = $currentTemp;
        } elseif ($permanent->gte($today)) {
            $base = $permanent;
        } else {
            $base = $today;
        }

        $bill->extra_date = $base->copy()->addDays($days)->format('Y-m-d');
        $bill->auto_disable = 1;
        $bill->save();
        $this->expireDate = Carbon::parse($bill->extra_date)->format('Y-m-d');

        $permLabel = $bill->auto_disable_date
            ? Carbon::parse($bill->auto_disable_date)->format('d M Y')
            : '—';
        flash()->success(
            __('Temporary expire +:days days → :temp. Permanent stay :perm.', [
                'days' => $days,
                'temp' => Carbon::parse($bill->extra_date)->format('d M Y'),
                'perm' => $permLabel,
            ])
        );
    }

    public function setExpireDate(): void
    {
        $this->validate([
            'expireDate' => 'required|date',
        ]);

        $bill = BillingInfo::where('customer_bill_unique_id', $this->customerId)->first();
        if (! $bill) {
            flash()->error('Billing not found.');

            return;
        }

        $bill->extra_date = Carbon::parse($this->expireDate)->format('Y-m-d');
        $bill->auto_disable = 1;
        $bill->save();
        $this->expireDate = Carbon::parse($bill->extra_date)->format('Y-m-d');

        $permLabel = $bill->auto_disable_date
            ? Carbon::parse($bill->auto_disable_date)->format('d M Y')
            : '—';
        flash()->success(
            __('Temporary expire set to :temp. Permanent stay :perm', [
                'temp' => Carbon::parse($bill->extra_date)->format('d M Y'),
                'perm' => $permLabel,
            ])
        );
    }

    public function clearTempExpire(): void
    {
        $bill = BillingInfo::where('customer_bill_unique_id', $this->customerId)->first();
        if (! $bill) {
            flash()->error('Billing not found.');

            return;
        }

        $bill->extra_date = null;
        $bill->save();
        $this->expireDate = now()->format('Y-m-d');

        flash()->success(__('Temporary expire cleared. Permanent date unchanged.'));
    }

    public function with(): array
    {
        $customer = $this->customer();
        $online = $this->isOnline($customer);
        $optical = app(CustomerOpticalPresenter::class)->forCustomer($customer, false);
        $gps = $customer->gpsCoordinates();
        $this->loadOnuForm($customer);

        $payments = PaymentSummary::where('customer_payment_unique_id', $customer->customer_unique_id)
            ->orderByDesc('id')
            ->limit(24)
            ->get();
        $collections = CollectionSummary::where('customer_collection_unique_id', $customer->customer_unique_id)
            ->orderByDesc('id')
            ->limit(24)
            ->get();

        $tickets = collect();
        if (class_exists(SupportTicket::class) && \Schema::hasTable('support_tickets')) {
            try {
                $tickets = SupportTicket::query()
                    ->when(
                        \Schema::hasColumn('support_tickets', 'customer_unique_id'),
                        fn ($q) => $q->where('customer_unique_id', $customer->customer_unique_id),
                        fn ($q) => $q->where('customer_id', $customer->id)
                    )
                    ->orderByDesc('id')
                    ->limit(8)
                    ->get();
            } catch (\Throwable) {
                $tickets = collect();
            }
        }

        $address = $customer->customerAddress
            ->map(function ($a) {
                return trim(implode(', ', array_filter([
                    $a->label_name,
                    $a->input_type_text,
                    $a->input_type_dropdown,
                    $a->input_type_textarea,
                ])));
            })
            ->filter()
            ->values();

        $firstBillNote = $customer->official?->note ?? '';
        $firstBillCycle = str_contains(strtolower($firstBillNote), 'next month') ? 'next_month' : 'this_month';

        return [
            'customer' => $customer,
            'online' => $online,
            'payments' => $payments,
            'collections' => $collections,
            'tickets' => $tickets,
            'addressLines' => $address,
            'optical' => $optical,
            'gps' => $gps,
            'firstBillCycle' => $firstBillCycle,
            'walletBalance' => (float) ($customer->billing?->advance ?? 0),
            'hasPortalToken' => app(\App\Services\Portal\CustomerPortalAccessService::class)->hasAccessToken($customer),
            'smsLogs' => \Schema::hasTable('customer_sms_logs')
                ? CustomerSmsLog::query()
                    ->where('customer_unique_id', $customer->customer_unique_id)
                    ->orderByDesc('id')
                    ->limit(80)
                    ->get()
                : collect(),
            'smsSentCount' => \Schema::hasTable('customer_sms_logs')
                ? CustomerSmsLog::query()->where('customer_unique_id', $customer->customer_unique_id)->where('status', 'success')->count()
                : 0,
            'smsFailCount' => \Schema::hasTable('customer_sms_logs')
                ? CustomerSmsLog::query()->where('customer_unique_id', $customer->customer_unique_id)->where('status', 'failed')->count()
                : 0,
        ];
    }

    public function render()
    {
        return view('livewire.customer-view')
            ->layout('layouts.app');
    }
}

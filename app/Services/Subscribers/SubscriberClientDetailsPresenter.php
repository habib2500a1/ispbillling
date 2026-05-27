<?php

namespace App\Services\Subscribers;

use App\Filament\Pages\BillCollectionDesk;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\NotificationLogResource;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Invoice;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Models\User;
use App\Support\NotificationEvent;
use App\Services\Network\CustomerConnectionStatusService;
use App\Services\Optical\SubscriberOpticalPowerPresenter;
use App\Support\MacAddress;
use App\Support\SubscriberType;

/**
 * Full ISP Digital–style Client Details (all stored subscriber fields for view + PDF parity).
 */
final class SubscriberClientDetailsPresenter
{
    /** @var list<string> */
    private const META_KNOWN_KEYS = [
        'static_ip', 'mac_binding', 'vlan', 'epon_port', 'onu_mac',
        'onu_rent', 'onu_installment', 'onu_deposit', 'router_rent',
        'gps_lat', 'gps_lng', 'collector_id', 'technician_id', 'branch_id',
        'installation_date', 'installation_charge', 'cable_length_m', 'installation_status',
        'portal_otp_login', 'portal_2fa',
        'notify_sms', 'notify_whatsapp', 'notify_email', 'notify_push',
        'auto_invoice', 'auto_pppoe', 'auto_onu', 'auto_activate', 'auto_suspend', 'auto_renew',
        'tag_vip', 'tag_gaming', 'tag_corporate', 'tag_late_payer',
        'discount_note', 'mikrotik_comment', 'legacy_id', 'legacy_client_id',
    ];

    public function __construct(
        private readonly SubscriberOpticalPowerPresenter $optical,
        private readonly CustomerConnectionStatusService $connectionStatus,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forCustomer(Customer $customer): array
    {
        $customer->loadMissing([
            'package:id,name,download_mbps,upload_mbps,price_monthly,vat_percent,billing_cycle_days,setup_fee',
            'area:id,name',
            'zone:id,name',
            'subzone:id,name',
            'reseller:id,name',
            'mikrotikServer:id,name,host',
            'pendingPackage:id,name',
            'activePppSession',
            'contacts',
        ]);

        $meta = is_array($customer->meta) ? $customer->meta : [];
        $ppp = $customer->activePppSession;

        $openBalance = (float) $customer->invoices()
            ->whereIn('status', ['open', 'partial', 'sent', 'overdue'])
            ->selectRaw('COALESCE(SUM(GREATEST(total - amount_paid, 0)), 0) as open_balance')
            ->value('open_balance');

        $lastPayment = Payment::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->orderByDesc('paid_at')
            ->first();

        $recentPayments = Payment::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->orderByDesc('paid_at')
            ->limit(15)
            ->with('recorder:id,name')
            ->get();

        $recentInvoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('issue_date')
            ->limit(15)
            ->get();

        $recentSmsLogs = NotificationLog::query()
            ->where('customer_id', $customer->id)
            ->where('channel', 'sms')
            ->orderByDesc('created_at')
            ->limit(100)
            ->with('smsDeliveryReport:id,notification_log_id,delivery_status,status_text,gateway_message_id,reported_at')
            ->get();

        $smsStats = [
            'total' => NotificationLog::query()
                ->where('customer_id', $customer->id)
                ->where('channel', 'sms')
                ->count(),
            'sent' => NotificationLog::query()
                ->where('customer_id', $customer->id)
                ->where('channel', 'sms')
                ->where('status', 'sent')
                ->count(),
            'failed' => NotificationLog::query()
                ->where('customer_id', $customer->id)
                ->where('channel', 'sms')
                ->where('status', 'failed')
                ->count(),
        ];

        $clientMac = $this->firstFilled(
            $ppp?->caller_id,
            $meta['mac_binding'] ?? null,
        );

        $username = $customer->mikrotik_secret_name
            ?: $customer->radius_username
            ?: $customer->customer_code;

        $conn = $this->connectionStatus->summary($customer);

        return [
            'customer' => $customer,
            'header' => [
                'client_code' => $customer->customer_code ?: (string) $customer->id,
                'client_name' => $customer->name,
                'phone' => $customer->phone ?: '—',
                'username' => $username,
                'initial' => mb_strtoupper(mb_substr($customer->name, 0, 1)),
                'status' => $customer->statusLabel(),
                'status_color' => $customer->statusColor(),
                'subscriber_type' => $customer->subscriberTypeLabel(),
                'subscriber_type_color' => $customer->subscriberTypeColor(),
                'online' => $customer->isPppOnline(),
                'connection_duration' => $conn['connection_duration'] ?? '—',
                'last_disconnect' => $conn['last_disconnect_formatted'],
                'portal_last_logout' => $conn['portal_last_logout_at'] ?? '—',
                'network' => $customer->network_access_state ?? 'active',
                'balance' => (float) $customer->account_balance,
                'open_balance' => round($openBalance, 2),
                'package' => $customer->package?->name ?? '—',
                'speed' => $customer->package
                    ? ($customer->package->download_mbps ?? '?').' / '.($customer->package->upload_mbps ?? '?').' Mbps'
                    : '—',
                'monthly_bill' => $customer->package?->price_monthly
                    ? number_format((float) $customer->package->price_monthly, 2).' BDT'
                    : '—',
                'valid_until' => $customer->service_expires_at?->format('d-M-Y') ?? '—',
                'activation_date' => $customer->joined_at?->format('d-M-Y') ?? '—',
                'off_date' => $customer->serviceOffDate()?->format('d-M-Y') ?? '—',
                'expired' => $customer->isServiceExpired(),
            ],
            'sections_overview' => $this->sectionsOverview($customer, $meta, $ppp, $clientMac, $username, $conn, $openBalance, $lastPayment),
            'sections' => [
                'identity' => $this->sectionIdentity($customer, $meta),
                'location' => $this->sectionLocation($customer, $meta),
                'connection' => $this->sectionConnection($customer, $meta, $ppp, $clientMac, $username, $conn),
                'billing' => $this->sectionBilling($customer, $meta, $openBalance, $lastPayment),
                'fees' => $this->sectionFees($customer, $meta),
                'installation' => $this->sectionInstallation($customer, $meta),
                'staff' => $this->sectionStaff($customer, $meta),
                'onu_billing' => $this->sectionOnuBilling($meta),
                'notifications' => $this->sectionNotifications($meta),
                'automation' => $this->sectionAutomation($meta),
                'tags' => $this->sectionTags($meta),
                'kyc' => $this->sectionKyc($customer),
                'system' => $this->sectionSystem($customer),
                'legacy_meta' => $this->sectionLegacyMeta($meta),
            ],
            'contacts' => $customer->contacts->map(fn (CustomerContact $c): array => [
                'label' => $c->label,
                'phone' => $c->phone,
                'primary' => $c->is_primary,
                'whatsapp' => $c->is_whatsapp,
            ])->all(),
            'optical' => $this->optical->forCustomer($customer),
            'recent_payments' => $recentPayments,
            'recent_invoices' => $recentInvoices,
            'recent_sms_logs' => $recentSmsLogs,
            'sms_stats' => $smsStats,
            'urls' => [
                'edit' => CustomerResource::getUrl('edit', ['record' => $customer]),
                'collect' => BillCollectionDesk::getUrl(['customer' => $customer->id]),
                'portal_login' => route('staff.subscribers.portal-login', ['customer' => $customer->getKey()]),
                'invoices' => InvoiceResource::getUrl('index', [
                    'tableFilters' => ['customer_id' => ['value' => (string) $customer->id]],
                ]),
                'pay_public' => url('/pay?code='.urlencode((string) $customer->customer_code)),
                'sms_log' => NotificationLogResource::getUrl('index', [
                    'tableFilters' => [
                        'customer_id' => ['value' => (string) $customer->id],
                        'channel' => ['value' => 'sms'],
                    ],
                ]),
            ],
            'sms_event_labels' => NotificationEvent::labels(),
        ];
    }

    /**
     * Essential fields for the default subscriber overview (no duplicate header stats).
     *
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $conn
     * @return array<string, array<string, string>>
     */
    private function sectionsOverview(
        Customer $customer,
        array $meta,
        mixed $ppp,
        ?string $clientMac,
        string $username,
        array $conn,
        float $openBalance,
        ?Payment $lastPayment,
    ): array {
        $pkg = $customer->package;
        $location = collect([
            $customer->area?->name,
            $customer->zone?->name,
            $customer->subzone?->name,
        ])->filter()->implode(' · ');

        return [
            'account' => array_filter([
                'Phone' => $customer->phone ?: null,
                'Email' => $customer->email ?: null,
                'Address' => $customer->address ?: null,
                'Area / Zone' => $location !== '' ? $location : null,
                'Reseller' => $customer->reseller?->name,
                'NID' => $customer->nid_number ?: null,
            ], fn ($v) => filled($v)),
            'billing' => array_filter([
                'Package' => $pkg?->name,
                'Speed' => $pkg
                    ? ($pkg->download_mbps ?? '?').' / '.($pkg->upload_mbps ?? '?').' Mbps'
                    : null,
                'Monthly bill' => $pkg?->price_monthly
                    ? number_format((float) $pkg->price_monthly, 2).' BDT'
                    : null,
                'Open due' => $openBalance > 0 ? number_format($openBalance, 2).' BDT' : null,
                'Wallet' => number_format((float) $customer->account_balance, 2).' BDT',
                'Service expires' => $customer->service_expires_at?->format('d M Y'),
                'Billing day' => $customer->billing_day ? 'Day '.$customer->billing_day : null,
                'Last payment' => $lastPayment
                    ? number_format((float) $lastPayment->amount, 2).' BDT · '.$lastPayment->paid_at?->format('d M Y')
                    : null,
                'Pending package' => $customer->pendingPackage?->name,
            ], fn ($v) => filled($v)),
            'connection' => array_filter([
                'PPP user' => $username,
                'Online' => $customer->isPppOnline() ? 'Yes' : 'No',
                'Client IP' => $this->firstFilled($ppp?->framed_ip, $meta['static_ip'] ?? null),
                'MAC' => $clientMac
                    ? (MacAddress::normalizeColon($clientMac) ?? $clientMac)
                    : null,
                'Router' => $customer->mikrotikServer?->name,
                'Network access' => ucfirst((string) ($customer->network_access_state ?? 'active')),
                'Uptime' => $customer->isPppOnline() ? ($conn['connection_duration'] ?? null) : null,
                'Last disconnect' => ($conn['last_disconnect_formatted'] ?? '—') !== '—'
                    ? $conn['last_disconnect_formatted']
                    : null,
                'ONU / EPON' => filled($meta['epon_port'] ?? null)
                    ? (string) $meta['epon_port']
                    : (filled($meta['onu_mac'] ?? null) ? (string) $meta['onu_mac'] : null),
            ], fn ($v) => filled($v)),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, string>
     */
    private function sectionIdentity(Customer $customer, array $meta): array
    {
        return [
            'Client ID' => $customer->customer_code ?: (string) $customer->id,
            'Client Name' => $customer->name,
            'UserName' => $customer->mikrotik_secret_name ?: $customer->radius_username ?: '—',
            'Phone' => $customer->phone ?: '—',
            'Email' => $customer->email ?: '—',
            'NID Number' => $customer->nid_number ?: '—',
            'Segment' => $customer->segment ?: '—',
            'Status' => $customer->statusLabel(),
            'Billing Category' => $customer->subscriberTypeLabel(),
            'Import Source' => $customer->import_source ?: 'manual',
            'Legacy ID' => (string) ($meta['legacy_id'] ?? $meta['legacy_client_id'] ?? '—'),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, string>
     */
    private function sectionLocation(Customer $customer, array $meta): array
    {
        return [
            'Address' => $customer->address ?: '—',
            'Area' => $customer->area?->name ?? '—',
            'Zone' => $customer->zone?->name ?? '—',
            'Sub Zone' => $customer->subzone?->name ?? '—',
            'GPS Latitude' => (string) ($meta['gps_lat'] ?? '—'),
            'GPS Longitude' => (string) ($meta['gps_lng'] ?? '—'),
            'Reseller' => $customer->reseller?->name ?? '—',
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, string>
     */
    private function sectionConnection(
        Customer $customer,
        array $meta,
        mixed $ppp,
        ?string $clientMac,
        string $username,
        array $conn,
    ): array {
        return [
            'UserName' => $username,
            'RADIUS Username' => $customer->radius_username ?: '—',
            'MacAddress' => $clientMac
                ? (MacAddress::normalizeColon($clientMac) ?? $clientMac)
                : '—',
            'IpAddress' => $this->firstFilled($ppp?->framed_ip, $meta['static_ip'] ?? null) ?: '—',
            'Static IP' => (string) ($meta['static_ip'] ?? '—'),
            'VLAN' => (string) ($meta['vlan'] ?? '—'),
            'MikroTik Server' => $customer->mikrotikServer?->name ?? '—',
            'Router Host' => $customer->mikrotikServer?->host ?? '—',
            'Network Access' => ucfirst((string) ($customer->network_access_state ?? 'active')),
            'PPP Online' => $customer->isPppOnline() ? 'Yes' : 'No',
            'Connected Since' => $conn['session_started_formatted'] ?? '—',
            'Connection Duration' => $conn['connection_duration'] ?? '—',
            'Last Disconnect (PPPoE)' => $conn['last_disconnect_formatted'],
            'Last Seen' => $customer->ppp_last_seen_at?->format('d-M-Y H:i') ?? '—',
            'Portal Last Login' => $customer->portal_last_login_at?->format('d-M-Y H:i') ?? '—',
            'Portal Last Logout' => $customer->portal_last_logout_at?->format('d-M-Y H:i') ?? '—',
            'MikroTik Synced' => $customer->mikrotik_synced_at?->format('d-M-Y H:i') ?? '—',
            'ONU MAC' => filled($meta['onu_mac'] ?? null)
                ? (MacAddress::normalizeColon((string) $meta['onu_mac']) ?? $meta['onu_mac'])
                : '—',
            'EPON Port' => (string) ($meta['epon_port'] ?? '—'),
            'MikroTik Comment' => (string) ($meta['mikrotik_comment'] ?? '—'),
            'PPP Password Set' => filled($customer->getAttributes()['mikrotik_ppp_password'] ?? null) ? 'Yes' : 'No',
            'Portal Login Set' => filled($customer->portal_password) ? 'Yes' : 'No',
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, string>
     */
    private function sectionBilling(Customer $customer, array $meta, float $openBalance, ?Payment $lastPayment): array
    {
        $pkg = $customer->package;

        return [
            'Package Name' => $pkg?->name ?? '—',
            'Download / Upload' => $pkg
                ? ($pkg->download_mbps ?? '?').' / '.($pkg->upload_mbps ?? '?').' Mbps'
                : '—',
            'Monthly Bill' => $pkg?->price_monthly
                ? number_format((float) $pkg->price_monthly, 2).' BDT'
                : '—',
            'VAT %' => $pkg?->vat_percent !== null ? (string) $pkg->vat_percent.'%' : '—',
            'Setup Fee' => $pkg?->setup_fee !== null ? number_format((float) $pkg->setup_fee, 2).' BDT' : '—',
            'Billing Mode' => ucfirst((string) ($customer->billing_mode ?? 'postpaid')),
            'Bill Generate Day' => (string) ($customer->billing_day ?? '—'),
            'Activation Date' => $customer->joined_at?->format('d-M-Y') ?? '—',
            'Expire Date' => $customer->service_expires_at?->format('d-M-Y') ?? '—',
            'Expire Day (month)' => $customer->service_expires_at
                ? (string) \App\Support\BillingDefaults::expireDayFromDate($customer->service_expires_at)
                : '—',
            'Line Off From' => $customer->serviceOffDate()?->format('d-M-Y') ?? '—',
            'Late Fee Grace' => ((int) $customer->grace_period_days).' days after due',
            'Wallet Balance' => number_format((float) $customer->account_balance, 2).' BDT',
            'Open Due' => number_format($openBalance, 2).' BDT',
            'Credit Limit' => $customer->credit_limit !== null
                ? number_format((float) $customer->credit_limit, 2).' BDT'
                : 'No limit',
            'Last Payment' => $lastPayment
                ? number_format((float) $lastPayment->amount, 2).' BDT · '.$lastPayment->paid_at?->format('d-M-Y')
                : '—',
            'Pending Package' => $customer->pendingPackage?->name ?? '—',
            'Pending Package From' => $customer->pending_package_effective_date?->format('d-M-Y') ?? '—',
            'Discount Note' => (string) ($meta['discount_note'] ?? '—'),
            'Auto Suspend Override' => $customer->auto_suspend_override === null
                ? 'Default'
                : ($customer->auto_suspend_override ? 'Allow auto off' : 'Never auto off'),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, string>
     */
    private function sectionFees(Customer $customer, array $meta): array
    {
        return [
            'Security Deposit Required' => number_format((float) $customer->security_deposit_required, 2).' BDT',
            'Security Deposit Collected' => number_format((float) $customer->security_deposit_collected, 2).' BDT',
            'Late Fee Fixed' => number_format((float) $customer->late_fee_fixed, 2).' BDT',
            'Late Fee %' => (string) ($customer->late_fee_percent ?? '0').'%',
            'Late Fee Period' => ucfirst((string) ($customer->late_fee_period ?? 'daily')),
            'Reconnection Fee' => number_format((float) $customer->reconnection_fee_amount, 2).' BDT',
            'Pending Reconnection Fee' => $customer->pending_reconnection_fee ? 'Yes' : 'No',
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, string>
     */
    private function sectionInstallation(Customer $customer, array $meta): array
    {
        return [
            'Installation Date' => filled($meta['installation_date'] ?? null)
                ? (string) $meta['installation_date']
                : '—',
            'Installation Charge' => isset($meta['installation_charge'])
                ? number_format((float) $meta['installation_charge'], 2).' BDT'
                : '—',
            'Cable Length (m)' => (string) ($meta['cable_length_m'] ?? '—'),
            'Installation Status' => ucfirst((string) ($meta['installation_status'] ?? '—')),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, string>
     */
    private function sectionStaff(Customer $customer, array $meta): array
    {
        return [
            'Collector' => $this->staffName($meta['collector_id'] ?? null),
            'Technician' => $this->staffName($meta['technician_id'] ?? null),
            'Branch' => $this->branchName($meta['branch_id'] ?? null),
            'Reseller' => $customer->reseller?->name ?? '—',
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, string>
     */
    private function sectionOnuBilling(array $meta): array
    {
        $network = is_array($meta['isp_digital_network'] ?? null) ? $meta['isp_digital_network'] : [];
        $fmt = static fn ($v): string => isset($v) && $v !== '' && (float) $v > 0
            ? number_format((float) $v, 2).' BDT'
            : '—';

        $rows = [
            'ONU Rent / month' => $fmt($meta['onu_rent'] ?? null),
            'ONU Installment' => $fmt($meta['onu_installment'] ?? null),
            'ONU Deposit' => $fmt($meta['onu_deposit'] ?? null),
            'Router Rent / month' => $fmt($meta['router_rent'] ?? null),
            'ISP Digital server' => (string) ($network['server'] ?? '—'),
            'Connection (ISP Digital)' => (string) ($network['connection_type'] ?? '—'),
            'Device (ISP Digital)' => (string) ($meta['device'] ?? $network['device'] ?? '—'),
            'Device MAC / Serial' => (string) ($meta['onu_mac'] ?? $network['device_mac_serial_no'] ?? '—'),
            'Fiber / Cable' => trim(implode(' · ', array_filter([
                filled($meta['fiber_code'] ?? null) ? 'Fiber: '.$meta['fiber_code'] : null,
                isset($meta['cable_length_m']) && (float) $meta['cable_length_m'] > 0
                    ? 'Cable: '.(float) $meta['cable_length_m'].' m'
                    : null,
            ]))) ?: '—',
            'ISP Digital sync' => filled($meta['isp_digital_details_synced_at'] ?? null)
                ? \Illuminate\Support\Carbon::parse((string) $meta['isp_digital_details_synced_at'])->format('d-M-Y H:i')
                : '—',
        ];

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, string>
     */
    private function sectionNotifications(array $meta): array
    {
        return [
            'SMS Alert' => $this->yesNo($meta['notify_sms'] ?? true),
            'WhatsApp Alert' => $this->yesNo($meta['notify_whatsapp'] ?? false),
            'Email Alert' => $this->yesNo($meta['notify_email'] ?? false),
            'Push Alert' => $this->yesNo($meta['notify_push'] ?? false),
            'Portal OTP Login' => $this->yesNo($meta['portal_otp_login'] ?? false),
            'Portal 2FA' => $this->yesNo($meta['portal_2fa'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, string>
     */
    private function sectionAutomation(array $meta): array
    {
        return [
            'Auto Invoice' => $this->yesNo($meta['auto_invoice'] ?? true),
            'Auto PPPoE on Router' => $this->yesNo($meta['auto_pppoe'] ?? true),
            'Auto ONU Provision' => $this->yesNo($meta['auto_onu'] ?? true),
            'Auto Activate Line' => $this->yesNo($meta['auto_activate'] ?? true),
            'Auto Suspend When Due' => $this->yesNo($meta['auto_suspend'] ?? true),
            'Auto Renew Prepaid' => $this->yesNo($meta['auto_renew'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, string>
     */
    private function sectionTags(array $meta): array
    {
        return [
            'VIP Tag' => $this->yesNo($meta['tag_vip'] ?? false),
            'Gaming Tag' => $this->yesNo($meta['tag_gaming'] ?? false),
            'Corporate Tag' => $this->yesNo($meta['tag_corporate'] ?? false),
            'Late Payer Tag' => $this->yesNo($meta['tag_late_payer'] ?? false),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function sectionKyc(Customer $customer): array
    {
        return [
            'KYC Status' => ucfirst((string) ($customer->kyc_status ?? 'pending')),
            'KYC Verified At' => $customer->kyc_verified_at?->format('d-M-Y H:i') ?? '—',
            'NID Front' => $customer->nid_front_path ? 'Uploaded' : '—',
            'NID Back' => $customer->nid_back_path ? 'Uploaded' : '—',
            'Photo' => $customer->photo_path ? 'Uploaded' : '—',
            'KYC Notes' => $customer->kyc_notes ?: '—',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function sectionSystem(Customer $customer): array
    {
        return [
            'Database ID' => (string) $customer->id,
            'Tenant ID' => (string) $customer->tenant_id,
            'Created At' => $customer->created_at?->format('d-M-Y H:i') ?? '—',
            'Updated At' => $customer->updated_at?->format('d-M-Y H:i') ?? '—',
            'Notes' => $customer->notes ?: '—',
        ];
    }

    /**
     * Extra meta keys from old system import (preserved in JSON).
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, string>
     */
    private function sectionLegacyMeta(array $meta): array
    {
        $out = [];
        foreach ($meta as $key => $value) {
            if (in_array($key, self::META_KNOWN_KEYS, true)) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $out['meta.'.$key] = $value === null || $value === '' ? '—' : (string) $value;
            } elseif (is_array($value)) {
                $out['meta.'.$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }

        return $out;
    }

    private function staffName(mixed $id): string
    {
        if ($id === null || $id === '') {
            return '—';
        }

        return User::query()->whereKey($id)->value('name') ?? ('User #'.$id);
    }

    private function branchName(mixed $id): string
    {
        if ($id === null || $id === '') {
            return '—';
        }

        return Branch::query()->whereKey($id)->value('name') ?? ('Branch #'.$id);
    }

    private function yesNo(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No';
    }

    private function firstFilled(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }
}

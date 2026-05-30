<?php

namespace App\Support;

/**
 * Partner portal capabilities — assigned per reseller (JSON) or defaulted by partner type.
 */
final class ResellerPortalPermission
{
    public const CUSTOMER_VIEW = 'portal.customer.view';

    public const CUSTOMER_CREATE = 'portal.customer.create';

    public const CUSTOMER_EDIT = 'portal.customer.edit';

    public const CUSTOMER_SUSPEND = 'portal.customer.suspend';

    public const PAYMENT_COLLECT = 'portal.payment.collect';

    public const INVOICE_GENERATE = 'portal.invoice.generate';

    public const BILLING_VIEW = 'portal.billing.view';

    public const ONU_VIEW = 'portal.onu.view';

    public const NETWORK_VIEW = 'portal.network.view';

    public const REPORTS_VIEW = 'portal.reports.view';

    public const SETTLEMENT_MANAGE = 'portal.settlement.manage';

    public const WALLET_VIEW = 'portal.wallet.view';

    public const COMMISSION_VIEW = 'portal.commission.view';

    public const TICKET_CREATE = 'portal.ticket.create';

    public const SUB_RESELLER_VIEW = 'portal.sub_reseller.view';

    public const INTEGRATIONS_MANAGE = 'portal.integrations.manage';

    public const STAFF_MANAGE = 'portal.staff.manage';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::CUSTOMER_VIEW => 'View subscribers',
            self::CUSTOMER_CREATE => 'Create subscriber',
            self::CUSTOMER_EDIT => 'Edit subscriber',
            self::CUSTOMER_SUSPEND => 'Suspend / reconnect',
            self::PAYMENT_COLLECT => 'Collect payments',
            self::INVOICE_GENERATE => 'Generate invoices',
            self::BILLING_VIEW => 'Billing & dues',
            self::ONU_VIEW => 'ONU / GPON monitoring',
            self::NETWORK_VIEW => 'MikroTik / traffic',
            self::REPORTS_VIEW => 'Reports & analytics',
            self::SETTLEMENT_MANAGE => 'Settlement requests',
            self::WALLET_VIEW => 'Wallet & transfers',
            self::COMMISSION_VIEW => 'Commission ledger',
            self::TICKET_CREATE => 'Support tickets',
            self::SUB_RESELLER_VIEW => 'Sub-resellers',
            self::INTEGRATIONS_MANAGE => 'SMS & payment integrations',
            self::STAFF_MANAGE => 'Manage staff accounts',
        ];
    }

    /** @return list<string> */
    public static function assignableToStaff(): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (string $permission): bool => $permission !== self::STAFF_MANAGE,
        ));
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::labels());
    }

    /**
     * Default portal access by partner type (admin can override per account).
     *
     * @return list<string>
     */
    public static function defaultsFor(string $franchiseType): array
    {
        $read = [
            self::CUSTOMER_VIEW,
            self::BILLING_VIEW,
            self::COMMISSION_VIEW,
            self::WALLET_VIEW,
        ];

        return match ($franchiseType) {
            ResellerType::MASTER_RESELLER => self::all(),
            ResellerType::FRANCHISE, ResellerType::AREA_DISTRIBUTOR => array_merge($read, [
                self::CUSTOMER_CREATE,
                self::CUSTOMER_EDIT,
                self::CUSTOMER_SUSPEND,
                self::PAYMENT_COLLECT,
                self::INVOICE_GENERATE,
                self::ONU_VIEW,
                self::NETWORK_VIEW,
                self::REPORTS_VIEW,
                self::SETTLEMENT_MANAGE,
                self::TICKET_CREATE,
                self::SUB_RESELLER_VIEW,
                self::STAFF_MANAGE,
            ]),
            ResellerType::SUB_RESELLER, ResellerType::LOCAL_PARTNER => array_merge($read, [
                self::CUSTOMER_EDIT,
                self::PAYMENT_COLLECT,
                self::REPORTS_VIEW,
                self::SETTLEMENT_MANAGE,
                self::TICKET_CREATE,
            ]),
            default => array_merge($read, [
                self::CUSTOMER_CREATE,
                self::CUSTOMER_EDIT,
                self::PAYMENT_COLLECT,
                self::INVOICE_GENERATE,
                self::REPORTS_VIEW,
                self::SETTLEMENT_MANAGE,
                self::TICKET_CREATE,
                self::STAFF_MANAGE,
            ]),
        };
    }
}

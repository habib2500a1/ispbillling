<?php

namespace App\Support;

use App\Models\Reseller;

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

    public const INVOICE_ADJUST = 'portal.invoice.adjust';

    public const INVOICE_EDIT = 'portal.invoice.edit';

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

    public const SUB_RESELLER_CREATE = 'portal.sub_reseller.create';

    public const CUSTOMER_TRANSFER = 'portal.customer.transfer';

    public const API_KEYS_MANAGE = 'portal.api_keys.manage';

    public const BRANDING_MANAGE = 'portal.branding.manage';

    public const RESELLER_BILLING_VIEW = 'portal.reseller_billing.view';

    public const ANNOUNCEMENTS_VIEW = 'portal.announcements.view';

    public const INTERNAL_TICKET_MANAGE = 'portal.internal_ticket.manage';

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
            self::INVOICE_ADJUST => 'Discount / waive invoices',
            self::INVOICE_EDIT => 'Edit invoice lines',
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
            self::SUB_RESELLER_CREATE => 'Create sub-resellers',
            self::CUSTOMER_TRANSFER => 'Transfer subscribers',
            self::API_KEYS_MANAGE => 'Manage API keys',
            self::BRANDING_MANAGE => 'White-label branding',
            self::RESELLER_BILLING_VIEW => 'Reseller billing & dues',
            self::ANNOUNCEMENTS_VIEW => 'Announcements',
            self::INTERNAL_TICKET_MANAGE => 'Internal support tickets',
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

    /**
     * Permissions that can be scoped on partner API keys (read-only /partner routes).
     *
     * @return list<string>
     */
    public static function assignableToApiKeys(): array
    {
        return [
            self::CUSTOMER_VIEW,
            self::BILLING_VIEW,
            self::COMMISSION_VIEW,
            self::WALLET_VIEW,
            self::SETTLEMENT_MANAGE,
            self::TICKET_CREATE,
            self::ONU_VIEW,
            self::NETWORK_VIEW,
            self::REPORTS_VIEW,
            self::SUB_RESELLER_VIEW,
            self::CUSTOMER_TRANSFER,
            self::RESELLER_BILLING_VIEW,
            self::ANNOUNCEMENTS_VIEW,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labelsForApiKeys(Reseller $reseller): array
    {
        $allowed = array_flip($reseller->portalPermissions());

        return array_filter(
            self::labels(),
            static fn (string $key): bool => in_array($key, self::assignableToApiKeys(), true)
                && isset($allowed[$key]),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::labels());
    }

    /**
     * Always available in partner portal (even when admin set a custom permission list).
     *
     * @return list<string>
     */
    public static function alwaysGranted(): array
    {
        return [
            self::ANNOUNCEMENTS_VIEW,
        ];
    }

    /**
     * Enterprise features enabled by default for standard resellers.
     *
     * @return list<string>
     */
    public static function enterpriseDefaults(): array
    {
        return [
            self::SUB_RESELLER_VIEW,
            self::CUSTOMER_TRANSFER,
            self::BRANDING_MANAGE,
            self::RESELLER_BILLING_VIEW,
            self::INTERNAL_TICKET_MANAGE,
        ];
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
            ResellerType::DISTRIBUTOR, ResellerType::FRANCHISE, ResellerType::AREA_DISTRIBUTOR => array_merge($read, [
                self::CUSTOMER_CREATE,
                self::CUSTOMER_EDIT,
                self::CUSTOMER_SUSPEND,
                self::PAYMENT_COLLECT,
                self::INVOICE_GENERATE,
                self::INVOICE_ADJUST,
                self::INVOICE_EDIT,
                self::ONU_VIEW,
                self::NETWORK_VIEW,
                self::REPORTS_VIEW,
                self::SETTLEMENT_MANAGE,
                self::TICKET_CREATE,
                self::SUB_RESELLER_VIEW,
                self::SUB_RESELLER_CREATE,
                self::CUSTOMER_TRANSFER,
                self::API_KEYS_MANAGE,
                self::BRANDING_MANAGE,
                self::RESELLER_BILLING_VIEW,
                self::ANNOUNCEMENTS_VIEW,
                self::INTERNAL_TICKET_MANAGE,
                self::STAFF_MANAGE,
            ]),
            ResellerType::SUB_RESELLER, ResellerType::LOCAL_PARTNER => array_merge($read, [
                self::CUSTOMER_EDIT,
                self::INVOICE_ADJUST,
                self::INVOICE_EDIT,
                self::PAYMENT_COLLECT,
                self::REPORTS_VIEW,
                self::SETTLEMENT_MANAGE,
                self::TICKET_CREATE,
            ]),
            default => array_merge($read, [
                self::CUSTOMER_CREATE,
                self::CUSTOMER_EDIT,
                self::CUSTOMER_SUSPEND,
                self::PAYMENT_COLLECT,
                self::INVOICE_GENERATE,
                self::INVOICE_ADJUST,
                self::INVOICE_EDIT,
                self::ONU_VIEW,
                self::NETWORK_VIEW,
                self::REPORTS_VIEW,
                self::SETTLEMENT_MANAGE,
                self::TICKET_CREATE,
                self::SUB_RESELLER_VIEW,
                self::SUB_RESELLER_CREATE,
                self::CUSTOMER_TRANSFER,
                self::BRANDING_MANAGE,
                self::RESELLER_BILLING_VIEW,
                self::INTERNAL_TICKET_MANAGE,
                self::STAFF_MANAGE,
            ]),
        };
    }
}

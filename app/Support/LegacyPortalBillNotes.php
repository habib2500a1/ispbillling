<?php

namespace App\Support;

final class LegacyPortalBillNotes
{
    public static function billMonthNote(string $billMonth): string
    {
        return BillingPortalLabel::name()." bill month: {$billMonth}";
    }

    public static function importedNote(): string
    {
        return 'Imported from '.BillingPortalLabel::name();
    }

    public static function serviceInvoiceNote(string|int $invoiceId, ?string $remarks = null): string
    {
        $remarks = trim((string) $remarks);

        return $remarks !== ''
            ? $remarks
            : BillingPortalLabel::name().' service invoice #'.$invoiceId;
    }

    public static function customerInvoiceNote(string|int $invoiceId, ?string $remarks = null): string
    {
        $remarks = trim((string) $remarks);

        return $remarks !== ''
            ? $remarks
            : BillingPortalLabel::name().' customer invoice #'.$invoiceId;
    }

    public static function parseBillMonth(?string $notes): ?string
    {
        if ($notes === null || trim($notes) === '') {
            return null;
        }

        $prefixes = [
            BillingPortalLabel::name().' bill month:',
            'legacy portal bill month:',
            'ISP Digital bill month:',
        ];

        foreach ($prefixes as $prefix) {
            $pattern = '/'.preg_quote($prefix, '/').'\s*([^·|]+)/iu';
            if (preg_match($pattern, $notes, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }
}

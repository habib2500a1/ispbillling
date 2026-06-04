<?php

namespace App\Services\Import;

use App\Models\Customer;
use App\Models\NotificationLog;
use Carbon\Carbon;
use Illuminate\Support\Str;

final class LegacyPortalSmsImporter
{
    public function __construct(
        private readonly int $tenantId = 1,
    ) {}

    /**
     * @return array{imported: int, skipped: int, customers: int}
     */
    public function importAll(LegacyPortalSessionClient $client, bool $force = false): array
    {
        $stats = ['imported' => 0, 'skipped' => 0, 'customers' => 0];
        $importer = new LegacyPortalBillingImporter;

        foreach ($importer->customersByLegacyHeaderId() as $headerId => $customer) {
            $stats['customers']++;
            $row = $this->importCustomer($client, $customer, (int) $headerId, $force);
            $stats['imported'] += $row['imported'];
            $stats['skipped'] += $row['skipped'];
        }

        return $stats;
    }

    /**
     * @return array{imported: int, skipped: int}
     */
    public function importCustomer(
        LegacyPortalSessionClient $client,
        Customer $customer,
        int $customerHeaderId,
        bool $force = false,
    ): array {
        $stats = ['imported' => 0, 'skipped' => 0];
        $start = 0;
        $length = 200;

        do {
            $page = $client->fetchCustomerMessagesHistoryPage($customerHeaderId, $start, $length);
            $rows = $page['aaData'];
            $total = $page['iTotalDisplayRecords'];

            foreach ($rows as $row) {
                $smsId = (int) ($row['SMSLogId'] ?? 0);
                if ($smsId < 1) {
                    $stats['skipped']++;

                    continue;
                }

                $exists = NotificationLog::query()
                    ->where('tenant_id', $this->tenantId)
                    ->where('customer_id', $customer->id)
                    ->where('meta->legacy_portal_sms_log_id', $smsId)
                    ->exists();

                if ($exists && ! $force) {
                    $stats['skipped']++;

                    continue;
                }

                $statusRaw = strtolower((string) ($row['status'] ?? ''));
                $status = str_contains($statusRaw, 'success') || str_contains($statusRaw, 'deliver')
                    ? 'sent'
                    : (str_contains($statusRaw, 'fail') ? 'failed' : 'pending');

                $sentAt = $this->parseDate($row['date'] ?? null) ?? now();

                $attrs = [
                    'tenant_id' => $this->tenantId,
                    'customer_id' => $customer->id,
                    'event' => Str::slug((string) ($row['smsTypeName'] ?? 'sms'), '_'),
                    'channel' => 'sms',
                    'recipient' => $customer->phone ?: $customer->customer_code,
                    'status' => $status,
                    'message' => trim((string) ($row['smsText'] ?? '')),
                    'error' => $status === 'failed' ? (string) ($row['status'] ?? null) : null,
                    'sent_at' => $sentAt,
                    'meta' => [
                        'import_source' => 'legacy_portal',
                        'legacy_portal_sms_log_id' => $smsId,
                        'sms_type' => $row['smsType'] ?? null,
                        'sms_type_name' => $row['smsTypeName'] ?? null,
                        'sent_by' => $row['sentBy'] ?? null,
                    ],
                ];

                if ($exists && $force) {
                    NotificationLog::query()
                        ->where('tenant_id', $this->tenantId)
                        ->where('customer_id', $customer->id)
                        ->where('meta->legacy_portal_sms_log_id', $smsId)
                        ->update($attrs);
                } else {
                    NotificationLog::query()->create($attrs);
                }

                $stats['imported']++;
            }

            $start += $length;
        } while ($start < $total && $rows !== []);

        return $stats;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}

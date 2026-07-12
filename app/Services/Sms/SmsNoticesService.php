<?php

namespace App\Services\Sms;

use App\Jobs\SendBulkSmsJob;
use App\Models\CustomersInfo;
use App\Models\NotificationLogs;
use App\Models\SmsTemplate;
use App\Services\Billing\BillingNoticesService;
use Codepagol\SmsBridge\Facades\SmsBridge;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * SMS Notice Board — billing-driven SMS/WhatsApp actions on Code Pagol.
 * Reuses BillingNoticesService + SendBulkSmsJob (no new schema).
 */
final class SmsNoticesService
{
    public function __construct(
        private readonly BillingNoticesService $billingNotices,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(int $dueSoonDays = 3, int $limit = 50): array
    {
        $notices = $this->billingNotices->payload($dueSoonDays, $limit);

        $templates = SmsTemplate::query()
            ->orderByDesc('is_active')
            ->orderBy('template_name')
            ->get()
            ->map(fn (SmsTemplate $t) => [
                'id' => $t->id,
                'name' => $t->template_name,
                'template' => $t->template,
                'is_active' => (bool) $t->is_active,
            ])
            ->all();

        $gateway = $this->gatewayStatus();

        $recentLogs = NotificationLogs::query()
            ->where('type', 'Bulk SMS')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (NotificationLogs $log) => [
                'id' => $log->id,
                'title' => $log->title,
                'status' => $log->status,
                'created_at' => optional($log->created_at)?->diffForHumans(),
            ])
            ->all();

        return [
            'updated_at' => $notices['updated_at'],
            'summary' => $notices['summary'],
            'sections' => $notices['sections'],
            'templates' => $templates,
            'gateway' => $gateway,
            'recent_logs' => $recentLogs,
            'placeholders' => [
                '{CUSTOMER_NAME}', '{CUSTOMER_ID}', '{PPPOE_USERNAME}', '{DUE_AMOUNT}',
                '{BILL_AMOUNT}', '{AUTO_TEMPORARY_DAY}', '{COMPANY_NAME}', '{COMPANY_MOBILE}',
            ],
        ];
    }

    /**
     * @param  list<string>  $customerUniqueIds
     * @return array{queued: int, skipped_no_mobile: int, skipped_missing: int}
     */
    public function queueSms(array $customerUniqueIds, string $message): array
    {
        $message = trim($message);
        if (strlen($message) < 5) {
            throw new \InvalidArgumentException('SMS message is too short.');
        }

        $uids = array_values(array_unique(array_filter(array_map('strval', $customerUniqueIds))));
        if ($uids === []) {
            throw new \InvalidArgumentException('Select at least one customer.');
        }

        $customers = CustomersInfo::query()
            ->whereIn('customer_unique_id', $uids)
            ->whereNull('deleted_at')
            ->get(['id', 'customer_unique_id', 'mobile']);

        $withMobile = $customers->filter(fn (CustomersInfo $c) => filled($c->mobile));
        $skippedNoMobile = $customers->count() - $withMobile->count();
        $skippedMissing = count($uids) - $customers->count();

        $ids = $withMobile->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            throw new \InvalidArgumentException('No selected customers have a mobile number.');
        }

        SendBulkSmsJob::dispatch($ids, $message, (int) Auth::id());

        return [
            'queued' => count($ids),
            'skipped_no_mobile' => $skippedNoMobile,
            'skipped_missing' => $skippedMissing,
        ];
    }

    /**
     * Build wa.me links for selected customers (manual WhatsApp, no API).
     *
     * @param  list<string>  $customerUniqueIds
     * @return list<array{name: string, mobile: string, url: string}>
     */
    public function whatsappLinks(array $customerUniqueIds, string $message = ''): array
    {
        $uids = array_values(array_unique(array_filter(array_map('strval', $customerUniqueIds))));
        if ($uids === []) {
            return [];
        }

        return CustomersInfo::query()
            ->whereIn('customer_unique_id', $uids)
            ->whereNull('deleted_at')
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->limit(50)
            ->get(['customer_name', 'mobile'])
            ->map(function (CustomersInfo $c) use ($message) {
                $digits = preg_replace('/\D+/', '', (string) $c->mobile) ?? '';
                if ($digits === '') {
                    return null;
                }
                // BD local 01xxxxxxxxx → 8801xxxxxxxxx
                if (str_starts_with($digits, '0') && strlen($digits) === 11) {
                    $digits = '88'.$digits;
                }
                $url = 'https://wa.me/'.$digits;
                if (trim($message) !== '') {
                    $url .= '?text='.rawurlencode($message);
                }

                return [
                    'name' => $c->customer_name,
                    'mobile' => $c->mobile,
                    'url' => $url,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{ok: bool, balance: mixed, profile: mixed, error: string|null}
     */
    private function gatewayStatus(): array
    {
        try {
            return [
                'ok' => true,
                'balance' => SmsBridge::balance(),
                'profile' => SmsBridge::profile(),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('SMS gateway status failed: '.$e->getMessage());

            return [
                'ok' => false,
                'balance' => null,
                'profile' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}

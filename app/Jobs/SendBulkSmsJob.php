<?php

namespace App\Jobs;

use App\Models\CustomersInfo;
use App\Models\NotificationLogs;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBulkSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $customerIds;
    public $messageTemplate;
    public $adminUserId;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(array $customerIds, string $messageTemplate, int $adminUserId)
    {
        $this->customerIds = $customerIds;
        $this->messageTemplate = $messageTemplate;
        $this->adminUserId = $adminUserId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $admin = User::find($this->adminUserId);
        $adminEmail = $admin ? $admin->email : 'System/Admin';

        $successfulIDs = [];
        $errorIDs = [];

        // Chunk process target customers to prevent memory exhaustion
        CustomersInfo::whereIn('id', $this->customerIds)
            ->with(['pppUser', 'billing'])
            ->chunk(50, function ($customers) use (&$successfulIDs, &$errorIDs) {
                foreach ($customers as $customer) {
                    if (empty($customer->mobile)) {
                        $errorIDs[] = $customer->customer_unique_id . ' (No mobile number)';
                        continue;
                    }

                    // Dynamically compile placeholders for the customer
                    $message = $this->compileMessage($customer, $this->messageTemplate);

                    try {
                        $response = app(\App\Services\Sms\SmsSender::class)->send($customer->mobile, $message);

                        if ($response && $response->isSuccessful()) {
                            $successfulIDs[] = $customer->customer_unique_id . ' (' . ($customer->pppUser->username ?? 'No PPPoE') . ')';
                        } else {
                            $errorMsg = $response ? $response->getMessage() : 'Unknown error';
                            $errorIDs[] = $customer->customer_unique_id . ' (' . ($customer->pppUser->username ?? 'No PPPoE') . ') - Error: ' . $errorMsg;
                        }
                    } catch (\Exception $e) {
                        Log::error("Bulk SMS gateway exception for: " . $customer->customer_unique_id . " - " . $e->getMessage());
                        $errorIDs[] = $customer->customer_unique_id . ' - Exception: ' . $e->getMessage();
                    }
                }
            });

        // Store notification logs for tracking the bulk campaign results
        if (!empty($successfulIDs)) {
            NotificationLogs::create([
                'title' => 'Bulk SMS Campaign Success (' . count($successfulIDs) . ' Sent)',
                'message' => 'Sent by: ' . $adminEmail . PHP_EOL . 'Recipients: ' . implode(', ', $successfulIDs),
                'status' => 'Delivered successfully',
                'type' => 'Bulk SMS',
            ]);
        }

        if (!empty($errorIDs)) {
            NotificationLogs::create([
                'title' => 'Bulk SMS Campaign Errors (' . count($errorIDs) . ' Failed)',
                'message' => 'Sent by: ' . $adminEmail . PHP_EOL . 'Failures: ' . implode(', ', $errorIDs),
                'status' => 'Delivered with errors',
                'type' => 'Bulk SMS',
            ]);
        }
    }

    /**
     * Replace dynamic placeholders with customer-specific data.
     */
    private function compileMessage(CustomersInfo $customer, string $template): string
    {
        return app(\App\Services\Sms\SmsPlaceholderRenderer::class)->render($template, [], $customer);
    }
}

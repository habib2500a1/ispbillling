<?php

namespace App\Jobs;

use App\Http\Controllers\SMSController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPaymentCollectionSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    /**
     * @param  array<string, mixed>  $smsData
     */
    public function __construct(public array $smsData) {}

    public function handle(): void
    {
        try {
            $response = app(SMSController::class)->paymentCollectionSMS($this->smsData);
            if ($response && ! $response->isSuccessful()) {
                Log::warning('Payment SMS not delivered: '.$response->getMessage());
            }
        } catch (\Throwable $e) {
            Log::error('Payment SMS failed: '.$e->getMessage());
        }
    }
}

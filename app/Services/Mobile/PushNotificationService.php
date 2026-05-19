<?php

namespace App\Services\Mobile;

use App\Models\Customer;
use App\Models\DeviceToken;
use App\Models\PushNotificationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    public function __construct(
        private readonly DeviceTokenService $deviceTokens,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendTo(Model $owner, string $app, string $title, string $body, array $data = []): int
    {
        $tokens = $this->deviceTokens->tokensFor($owner, $app);
        if ($tokens->isEmpty()) {
            return 0;
        }

        $tenantId = $owner instanceof Customer ? $owner->tenant_id : ($owner instanceof User ? $owner->tenant_id : null);

        $log = PushNotificationLog::query()->create([
            'tenant_id' => $tenantId,
            'tokenable_type' => $owner::class,
            'tokenable_id' => $owner->getKey(),
            'app' => $app,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'status' => 'pending',
        ]);

        $sent = 0;
        if (config('mobile.fcm_enabled') && config('mobile.fcm_server_key')) {
            foreach ($tokens as $device) {
                if ($this->sendFcm($device, $title, $body, $data)) {
                    $sent++;
                }
            }
        }

        $log->update([
            'status' => $sent > 0 ? 'sent' : 'skipped',
            'sent_at' => $sent > 0 ? now() : null,
        ]);

        return $sent;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sendFcm(DeviceToken $device, string $title, string $body, array $data): bool
    {
        try {
            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'key='.config('mobile.fcm_server_key'),
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $device->token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'default',
                ],
                'data' => $data,
                'priority' => 'high',
            ]);

            if ($response->successful()) {
                $device->update(['last_used_at' => now()]);

                return true;
            }

            Log::warning('FCM push failed', ['status' => $response->status(), 'body' => $response->body()]);
        } catch (\Throwable $e) {
            Log::warning('FCM push exception', ['error' => $e->getMessage()]);
        }

        return false;
    }
}

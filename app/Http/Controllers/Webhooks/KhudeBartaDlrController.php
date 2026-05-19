<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\SmsDeliveryReport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * KhudeBarta delivery report (DLR) — configure Query URL in portal.
 *
 * @see https://portal.khudebarta.com/Softifybd/#/technical-details
 */
class KhudeBartaDlrController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $apiKey = (string) $request->query('apikey', $request->query('apiKey', ''));
        $secretKey = (string) $request->query('secretkey', $request->query('secretKey', ''));

        if (! $this->credentialsValid($apiKey, $secretKey)) {
            Log::warning('khudebarta.dlr.unauthorized', ['ip' => $request->ip()]);

            return response('UNAUTHORIZED', 401);
        }

        $messageId = (string) $request->query('messageid', $request->query('messageId', $request->query('Message_ID', '')));
        if ($messageId === '') {
            return response('MISSING_MESSAGE_ID', 400);
        }

        $status = (string) $request->query('status', $request->query('Status', $request->query('Text', '')));
        $statusText = (string) $request->query('StatusDescription', $request->query('statusDescription', ''));

        $log = NotificationLog::query()
            ->withoutGlobalScopes()
            ->where('channel', 'sms')
            ->where('meta->gateway_message_id', $messageId)
            ->orderByDesc('id')
            ->first();

        $deliveryStatus = $this->normalizeDeliveryStatus($status, $statusText);

        SmsDeliveryReport::query()->updateOrCreate(
            [
                'gateway' => 'khudebarta',
                'gateway_message_id' => $messageId,
            ],
            [
                'tenant_id' => $log?->tenant_id,
                'notification_log_id' => $log?->id,
                'recipient' => $request->query('toUser', $request->query('to', $log?->recipient)),
                'delivery_status' => $deliveryStatus,
                'status_text' => $statusText !== '' ? $statusText : $status,
                'payload' => $request->query(),
                'reported_at' => now(),
            ],
        );

        if ($log !== null) {
            $meta = is_array($log->meta) ? $log->meta : [];
            $meta['dlr_status'] = $deliveryStatus;
            $meta['dlr_at'] = now()->toIso8601String();
            $log->forceFill(['meta' => $meta])->saveQuietly();

            if ($deliveryStatus === 'delivered') {
                $log->forceFill(['status' => 'sent'])->saveQuietly();
            } elseif (in_array($deliveryStatus, ['failed', 'rejected', 'undelivered'], true)) {
                $log->forceFill([
                    'status' => 'failed',
                    'error' => $statusText !== '' ? $statusText : $status,
                ])->saveQuietly();
            }
        }

        return response('OK', 200);
    }

    private function credentialsValid(string $apiKey, string $secretKey): bool
    {
        $expectedKey = (string) config('notifications.sms.api_key', '');
        $expectedSecret = (string) config('notifications.sms.secret_key', '');

        if ($expectedKey === '' || $expectedSecret === '') {
            return false;
        }

        return hash_equals($expectedKey, $apiKey) && hash_equals($expectedSecret, $secretKey);
    }

    private function normalizeDeliveryStatus(string $status, string $statusText): string
    {
        $blob = strtolower($status.' '.$statusText);

        if (str_contains($blob, 'fail') || str_contains($blob, 'reject') || str_contains($blob, 'undeliver')) {
            return 'failed';
        }
        if (preg_match('/\bdelivered\b/', $blob) || str_contains($blob, 'acceptd') || $status === '0') {
            return 'delivered';
        }
        if (str_contains($blob, 'pend') || str_contains($blob, 'queue')) {
            return 'pending';
        }

        return $status !== '' ? strtolower($status) : 'unknown';
    }
}

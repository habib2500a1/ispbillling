<?php

namespace App\Services\Notifications\Gateways;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * KhudeBarta / Softifybd HTTP SMS (JSON).
 *
 * @see https://portal.khudebarta.com/Softifybd/#/technical-details
 */
final class KhudeBartaSmsGateway
{
    /**
     * @param  array<string, mixed>  $context
     * @return string|null Gateway message ID (for DLR)
     */
    public function send(string $phone, string $message, array $context = []): ?string
    {
        $apiKey = (string) config('notifications.sms.api_key', '');
        $secretKey = (string) config('notifications.sms.secret_key', '');
        $callerId = (string) config('notifications.sms.sender_id', '');
        $url = rtrim((string) config('notifications.sms.api_url', 'http://portal.khudebarta.com:3775/sendtext'), '/');

        if ($apiKey === '' || $secretKey === '') {
            throw new \RuntimeException('KhudeBarta SMS: API key and secret key are required.');
        }

        if ($callerId === '') {
            throw new \RuntimeException('KhudeBarta SMS: Caller ID (sender) is required.');
        }

        $toUser = $this->normalizePhone($phone);
        if ($toUser === '') {
            throw new \InvalidArgumentException('Invalid phone number for KhudeBarta SMS.');
        }

        $payload = [
            'apikey' => $apiKey,
            'secretkey' => $secretKey,
            'callerID' => $callerId,
            'toUser' => $toUser,
            'messageContent' => $message,
            'hash' => $this->buildHash($apiKey, $secretKey, $callerId, $toUser, $message),
        ];

        $response = Http::timeout((int) config('notifications.sms.timeout', 30))
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'KhudeBarta SMS HTTP '.$response->status().': '.$response->body()
            );
        }

        if (! $this->responseIndicatesSuccess($response->json(), $response->body())) {
            Log::warning('khudebarta.sms.rejected', [
                'to' => $toUser,
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('KhudeBarta SMS rejected: '.$response->body());
        }

        $json = $response->json();
        $messageId = is_array($json) ? (string) ($json['Message_ID'] ?? $json['message_id'] ?? '') : '';

        if ($messageId !== '' && isset($context['notification_log_id'])) {
            $log = \App\Models\NotificationLog::query()
                ->withoutGlobalScopes()
                ->find((int) $context['notification_log_id']);
            if ($log !== null) {
                $meta = is_array($log->meta) ? $log->meta : [];
                $meta['gateway'] = 'khudebarta';
                $meta['gateway_message_id'] = $messageId;
                $log->forceFill(['meta' => $meta])->saveQuietly();
            }
        }

        return $messageId !== '' ? $messageId : null;
    }

    public function buildHash(
        string $apiKey,
        string $secretKey,
        string $callerId,
        string $toUser,
        string $message,
    ): string {
        $formula = (string) config('notifications.sms.khudebarta_hash_formula', 'apikey_secretkey_callerID_toUser_messageContent');

        $concat = match ($formula) {
            'secretkey_toUser_messageContent' => $secretKey.$toUser.$message,
            'apikey_secretkey_toUser_messageContent' => $apiKey.$secretKey.$toUser.$message,
            'apikey_secretkey_callerID_toUser_messageContent' => $apiKey.$secretKey.$callerId.$toUser.$message,
            default => $apiKey.$secretKey.$callerId.$toUser.$message,
        };

        $hash = md5($concat);

        return config('notifications.sms.khudebarta_hash_uppercase', false)
            ? strtoupper($hash)
            : strtolower($hash);
    }

    /**
     * @param  mixed  $json
     */
    private function responseIndicatesSuccess(mixed $json, string $rawBody): bool
    {
        if (is_array($json)) {
            if (isset($json['Status']) && (string) $json['Status'] === '0') {
                return true;
            }
            if (isset($json['Text']) && strtoupper((string) $json['Text']) === 'ACCEPTD') {
                return true;
            }

            foreach (['status', 'Status', 'response', 'Response', 'code', 'Code', 'success'] as $key) {
                if (! array_key_exists($key, $json)) {
                    continue;
                }
                $val = strtolower((string) $json[$key]);
                if (in_array($val, ['0', 'success', 'ok', '200', 'sent', 'true', 'accepted', 'acceptd'], true)) {
                    return true;
                }
                if (in_array($val, ['failed', 'error', 'false', 'reject', 'rejected'], true)) {
                    return false;
                }
            }

            if (isset($json['message']) && is_string($json['message'])) {
                $msg = strtolower($json['message']);
                if (str_contains($msg, 'success') || str_contains($msg, 'submitted')) {
                    return true;
                }
            }
        }

        $body = strtolower($rawBody);

        return str_contains($body, 'success')
            || str_contains($body, 'submitted')
            || str_contains($body, '"status":0')
            || str_contains($body, '"status":"0"');
    }

    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '880')) {
            return $digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '88'.$digits;
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '1')) {
            return '880'.$digits;
        }

        return $digits;
    }
}

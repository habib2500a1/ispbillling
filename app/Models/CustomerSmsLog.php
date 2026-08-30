<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerSmsLog extends Model
{
    protected $fillable = [
        'customer_unique_id',
        'mobile',
        'body',
        'source',
        'status',
        'error',
        'sent_by',
    ];

    public static function record(?string $customerId, ?string $mobile, string $body, string $source, mixed $response): ?self
    {
        if (! \Schema::hasTable('customer_sms_logs')) {
            return null;
        }

        if (! $customerId && $mobile) {
            $customerId = CustomersInfo::query()
                ->where('mobile', $mobile)
                ->value('customer_unique_id');
        }

        $ok = false;
        $error = null;
        if ($response) {
            if (method_exists($response, 'isSuccessful')) {
                $ok = (bool) $response->isSuccessful();
            } elseif (isset($response->success)) {
                $ok = (bool) $response->success;
            }
            if (! $ok && method_exists($response, 'getMessage')) {
                $error = $response->getMessage();
            } elseif (! $ok) {
                $error = $response->message ?? 'Failed';
            }
        } else {
            $error = 'No response';
        }

        return self::create([
            'customer_unique_id' => $customerId,
            'mobile' => $mobile,
            'body' => $body,
            'source' => $source,
            'status' => $ok ? 'success' : 'failed',
            'error' => $error,
            'sent_by' => auth()->user()?->email ?? 'system',
        ]);
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            'profile' => 'Profile',
            'payment' => 'Payment',
            'bill_alert' => 'Bill alert',
            'expire_alert' => 'Expire alert',
            'disable_alert' => 'Disable alert',
            'collection_delete' => 'Collection delete',
            'resend' => 'Resend',
            default => ucfirst(str_replace('_', ' ', $this->source)),
        };
    }
}

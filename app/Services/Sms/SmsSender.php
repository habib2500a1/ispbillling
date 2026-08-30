<?php

namespace App\Services\Sms;

use Codepagol\SmsBridge\Facades\SmsBridge;
use Codepagol\SmsBridge\Models\SmsGateway;
use Codepagol\SmsBridge\Response\SmsResponse;

final class SmsSender
{
    public function hasRealGateway(): bool
    {
        try {
            return SmsGateway::query()
                ->where('is_active', true)
                ->whereRaw('LOWER(name) <> ?', ['log'])
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    public function send(string $to, string $message): SmsResponse
    {
        if (trim($to) === '') {
            return new SmsResponse(false, 'Recipient missing');
        }

        if (! $this->hasRealGateway()) {
            return new SmsResponse(false, 'SMS gateway not configured');
        }

        $response = SmsBridge::to($to)->message($message)->send();
        if ($response instanceof SmsResponse) {
            if ($response->isSuccessful() && str_contains(strtolower($response->getMessage()), 'logged')) {
                return new SmsResponse(false, 'SMS gateway not configured');
            }

            return $response;
        }

        return new SmsResponse(false, 'SMS send failed');
    }
}

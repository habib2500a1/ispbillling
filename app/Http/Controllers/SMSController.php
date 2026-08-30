<?php

namespace App\Http\Controllers;

use App\Models\CustomerSmsLog;
use App\Models\SmsTemplate;
use App\Services\Sms\SmsPlaceholderRenderer;
use App\Services\Sms\SmsSender;
use Codepagol\SmsBridge\Response\SmsResponse;

class SMSController extends Controller
{
    public function allCustomersSMS(array $data)
    {
        return $this->sendTemplate('all_customers', $data, 'bill_alert');
    }

    public function paymentCollectionSMS(array $data)
    {
        return $this->sendTemplate('payment_collection', $data, 'payment');
    }

    public function collectionDeleteSMS(array $data)
    {
        return $this->sendTemplate('collection_delete', $data, 'collection_delete');
    }

    public function sendCustomSms(array $data): SmsResponse
    {
        if (empty($data['recipient']) || empty($data['message'])) {
            return new SmsResponse(false, 'Recipient and message are required');
        }

        $message = app(SmsPlaceholderRenderer::class)->render(
            (string) $data['message'],
            $data,
            $data['customer'] ?? null
        );

        return $this->sendAndLog($data, $message, $data['source'] ?? 'custom');
    }

    protected function sendTemplate(string $templateName, array $data, string $source): SmsResponse
    {
        $template = SmsTemplate::where('template_name', $templateName)->first();

        if (! $template) {
            return new SmsResponse(false, 'Template not found');
        }

        if ($template->is_active != 1) {
            return new SmsResponse(false, 'Template is disabled');
        }

        $message = app(SmsPlaceholderRenderer::class)->render(
            (string) $template->template,
            $data,
            $data['customer'] ?? null
        );

        return $this->sendAndLog($data, $message, $source);
    }

    protected function sendAndLog(array $data, string $message, string $source): SmsResponse
    {
        $response = app(SmsSender::class)->send((string) ($data['recipient'] ?? ''), $message);

        CustomerSmsLog::record(
            $data['customer_id'] ?? $data['customer_unique_id'] ?? null,
            $data['recipient'] ?? null,
            $message,
            $source,
            $response
        );

        return $response;
    }
}

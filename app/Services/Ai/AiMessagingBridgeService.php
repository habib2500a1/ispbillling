<?php

namespace App\Services\Ai;

use App\Models\Customer;
use App\Services\WhatsApp\WhatsAppOutboundService;

final class AiMessagingBridgeService
{
    public function __construct(
        private readonly AiSettingsService $settings,
        private readonly AiCustomerAssistantService $customerAi,
        private readonly WhatsAppOutboundService $whatsapp,
    ) {}

    public function handleWhatsAppText(string $phoneDigits, string $text): ?string
    {
        if (! $this->settings->customerAiEnabled()) {
            return null;
        }

        $customer = $this->findCustomerByPhone($phoneDigits);
        if ($customer === null) {
            return preg_match('/[\x{0980}-\x{09FF}]/u', $text)
                ? 'আপনার নম্বর দিয়ে কোনো সক্রিয় অ্যাকাউন্ট পাওয়া যায়নি। কাস্টমার কোড দিয়ে /pay থেকে বিল দেখুন।'
                : 'No active account found for this number. Use /pay with your customer code.';
        }

        $result = $this->customerAi->reply($customer, $text);

        return (string) ($result['reply'] ?? '');
    }

    public function sendWhatsAppReply(string $phoneDigits, string $text): void
    {
        $reply = $this->handleWhatsAppText($phoneDigits, $text);
        if ($reply !== null && $reply !== '') {
            $this->whatsapp->sendText($phoneDigits, $reply);
        }
    }

    private function findCustomerByPhone(string $phoneDigits): ?Customer
    {
        $digits = preg_replace('/\D+/', '', $phoneDigits) ?? '';
        if ($digits === '') {
            return null;
        }

        return Customer::withoutGlobalScopes()
            ->where('phone', $digits)
            ->orWhere('phone', $phoneDigits)
            ->orderByDesc('id')
            ->first();
    }
}

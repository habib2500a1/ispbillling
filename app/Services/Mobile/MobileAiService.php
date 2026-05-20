<?php

namespace App\Services\Mobile;

use App\Models\Customer;
use App\Services\Portal\CustomerBandwidthService;
use App\Services\Portal\CustomerOnuOpticalService;

final class MobileAiService
{
    public function __construct(
        private readonly CustomerOnuOpticalService $onu,
        private readonly CustomerBandwidthService $bandwidth,
    ) {}

    /**
     * @return array{reply: string, hints: list<string>}
     */
    public function reply(Customer $customer, string $question): array
    {
        $q = strtolower(trim($question));
        $customer->loadMissing('package');
        $onu = $this->onu->snapshot($customer);
        $live = $this->bandwidth->liveStats($customer);
        $hints = [];

        if (str_contains($q, 'slow') || str_contains($q, 'speed')) {
            $hints[] = 'Check live usage in the Usage tab.';
            if (! ($live['online'] ?? false)) {
                return [
                    'reply' => 'Your connection appears offline. Please check power to the ONU/router or open a support ticket.',
                    'hints' => $hints,
                ];
            }
            $down = $live['download_human'] ?? '—';

            return [
                'reply' => "You are online. Current download speed is about {$down}. If it feels slow, try rebooting the router from Support or contact us for a line check.",
                'hints' => $hints,
            ];
        }

        if (str_contains($q, 'onu') || str_contains($q, 'red') || str_contains($q, 'signal') || str_contains($q, 'fiber')) {
            if (! ($onu['linked'] ?? false)) {
                return [
                    'reply' => 'No ONU is linked to your account yet. Please share your EPON port details in a support ticket.',
                    'hints' => ['Open Support → New ticket'],
                ];
            }
            $rx = $onu['rx_dbm'] ?? 'unknown';
            $label = $onu['rx_level_label'] ?? 'Unknown';

            return [
                'reply' => "ONU signal: RX {$rx} dBm ({$label}). ".($label === 'Critical' || $label === 'Warning'
                    ? 'Weak signal — our team can schedule a visit.'
                    : 'Signal looks acceptable.'),
                'hints' => ['View ONU details in Usage tab'],
            ];
        }

        if (str_contains($q, 'bill') || str_contains($q, 'pay') || str_contains($q, 'due')) {
            $due = (float) $customer->openInvoiceBalance();

            return [
                'reply' => 'Your open balance is '.number_format($due, 2).' BDT. Pay from the Bills tab or Payment section in the app.',
                'hints' => ['Bills → Pay'],
            ];
        }

        return [
            'reply' => 'I can help with slow internet, ONU/signal, billing, and tickets. Try: "Why is my internet slow?" or "What is my bill due?"',
            'hints' => ['Support → New ticket for other issues'],
        ];
    }
}

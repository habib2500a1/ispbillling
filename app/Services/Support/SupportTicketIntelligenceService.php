<?php

namespace App\Services\Support;

use App\Models\Customer;
use App\Models\SupportTicket;
use App\Support\SupportCategories;

/**
 * AI-style ticket analysis — category hints from description + live network telemetry.
 */
final class SupportTicketIntelligenceService
{
    /**
     * @return list<array{label: string, detail: string, tone: string}>
     */
    public function analyze(?Customer $customer, ?string $description, ?SupportTicket $ticket = null): array
    {
        $suggestions = [];
        $text = strtolower(trim((string) $description));

        if ($text !== '') {
            $suggestions = array_merge($suggestions, $this->analyzeText($text));
        }

        if ($customer !== null) {
            $suggestions = array_merge($suggestions, $this->analyzeNetwork($customer));
        }

        if ($ticket !== null && filled($ticket->issue_type)) {
            $suggestions[] = [
                'label' => 'Category',
                'detail' => SupportCategories::groupLabel($ticket->issue_type).' · '.SupportCategories::label($ticket->issue_type),
                'tone' => 'info',
            ];
        }

        return array_slice($suggestions, 0, 6);
    }

    /**
     * @return list<array{label: string, detail: string, tone: string}>
     */
    private function analyzeText(string $text): array
    {
        $out = [];

        if (preg_match('/not working|no internet|offline|disconnect/', $text)) {
            $out[] = [
                'label' => 'Suggested category',
                'detail' => 'No Internet / ONU Offline',
                'tone' => 'warn',
            ];
        }

        if (preg_match('/slow|speed|buffer|download/', $text)) {
            $out[] = [
                'label' => 'Suggested category',
                'detail' => 'Slow Speed',
                'tone' => 'info',
            ];
        }

        if (preg_match('/bill|due|payment|invoice|recharge/', $text)) {
            $out[] = [
                'label' => 'Suggested category',
                'detail' => 'Billing issue',
                'tone' => 'info',
            ];
        }

        if (preg_match('/fiber|cut|los|signal|optical|pon/', $text)) {
            $out[] = [
                'label' => 'Possible cause',
                'detail' => 'Fiber / optical path issue',
                'tone' => 'critical',
            ];
        }

        return $out;
    }

    /**
     * @return list<array{label: string, detail: string, tone: string}>
     */
    private function analyzeNetwork(Customer $customer): array
    {
        $out = [];
        $customer->loadMissing(['onuDevice.olt']);
        $onu = $customer->primaryOnu();
        $online = $customer->isPppOnline();

        if (! $online) {
            $lastSeen = $onu?->last_polled_at?->diffForHumans()
                ?? $customer->lastEndedPppSession?->ended_at?->diffForHumans()
                ?? 'unknown';

            $out[] = [
                'label' => 'ONU / PPP offline',
                'detail' => 'Last seen '.$lastSeen,
                'tone' => 'warn',
            ];
        }

        if ($onu !== null && $onu->rx_power_dbm !== null) {
            $rx = (float) $onu->rx_power_dbm;
            if ($rx <= -30) {
                $out[] = [
                    'label' => 'Weak RX signal',
                    'detail' => $rx.' dBm — possible fiber issue',
                    'tone' => 'critical',
                ];
            } elseif ($rx <= -27) {
                $out[] = [
                    'label' => 'Low signal warning',
                    'detail' => $rx.' dBm — check patch / splitter',
                    'tone' => 'warn',
                ];
            }
        }

        if ($onu !== null) {
            $status = strtolower((string) ($onu->onu_oper_status ?? ''));
            if (in_array($status, ['los', 'dying_gasp'], true)) {
                $out[] = [
                    'label' => 'ONU LOS',
                    'detail' => 'Loss of signal — fiber break suspected',
                    'tone' => 'critical',
                ];
            }
        }

        if ($customer->status === 'suspended') {
            $out[] = [
                'label' => 'Account suspended',
                'detail' => 'Verify billing before field dispatch',
                'tone' => 'info',
            ];
        }

        return $out;
    }
}
